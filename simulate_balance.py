"""
PVP 三流派平衡模擬器
目標：三組對戰（攻擊vs防禦 / 攻擊vs血量 / 防禦vs血量）勝率各自落在 45%~55%
調整方式：只動技能數值，不動角色基礎屬性
"""
import sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
import random
import copy

# ── 角色基礎屬性（固定不變）────────────────────────────────────────────────
# 各流派屬性分配哲學（Level 15，73 點訓練點 + 裝備加成）：
#   攻擊流：大量投入ATK，適量HP確保不被秒殺
#   防禦流：大量投入DEF，適量HP讓鋼鐵意志有意義
#   血量流：大量投入HP（約1.35x攻擊流），小量DEF，讓生命脈動有感
CHARS = {
    'assault':  {'hp': 480, 'atk': 225, 'def': 25,  'crit': 11, 'dodge': 11},
    'guardian': {'hp': 520, 'atk': 105, 'def': 62,  'crit': 11, 'dodge': 11},
    'vitality': {'hp': 650, 'atk': 118, 'def': 25,  'crit': 11, 'dodge': 11},
}

# ── 技能可調參數（全9節點狀態）──────────────────────────────────────────────
# 機制變更說明：
#   血肉渴望 → 改為吃「最大HP」比例（不隨血量下降），讓高HP流派成為雙面刃
#   不滅之軀 → 免疫改為「減傷50%」而非完全免疫，削弱無限存活感
PARAMS = {
    'assault': {
        'blood_use_max_hp': True,   # ★機制變更：吃敵方最大HP而非當前HP
        'blood_pct':        0.020,  # 血肉渴望比例（最大HP基準）
        'blood_crit_bonus': True,   # 節點3：觸發後下回合爆擊+10%
        'pierce_pct':       0.100,  # 穿心一擊：敵方最大HP比例真實傷害
        'pierce_cd':        3,      # 穿心冷卻（回合數）
        'hunt_threshold':   0.60,   # 獵殺本能：低於此HP比例觸發
        'hunt_bonus_hi':    0.15,   # 第一段加成（threshold以下）
        'hunt_bonus_lo':    0.15,   # 第二段加成（HP<25%額外）
    },
    'guardian': {
        'thorns_pct':        0.65,  # 荊棘之壁：反彈比例
        'thorns_cd':         4,     # 反彈週期（回合數）
        'thorns_heal':       15,    # 節點3：爆發後回血
        'thorns_threshold':  0.18,  # ★新機制：單次傷害超過 max_hp×此比例 才觸發加乘
        'thorns_large_mult': 1.50,  # ★新機制：大傷害的荊棘加乘倍率（自然懲罰高爆發）
        'iw_threshold':      0.50,  # 鋼鐵意志：觸發HP門檻
        'iw_mult':           1.40,  # 鋼鐵意志：防禦倍率
        'iw_heal':           20,    # 節點7：首次觸發回血
        'vengeance':         2,     # 報復之刃：保證暴擊次數
    },
    'vitality': {
        'heal_pct':          0.04,  # 生命脈動：每回合回復最大HP比例
        'heal_atk_bonus':    3,     # 節點3：回復觸發時ATK+3
        'corr_per_hit':      0.05,  # ★新機制：侵蝕改為比例型，每次 +5% DEF削減
        'corr_cap':          0.50,  # 侵蝕上限（最多削除50% DEF）
        'corr_true_dmg':     True,  # 節點7：真實傷害 = floor(base_def × corr_pct × ratio)
        'corr_true_ratio':   0.80,
        'revive_pct':        0.25,  # 不滅之軀：復活HP比例
        'revive_dmg_reduce': 0.50,  # 復活後受傷減半
    },
}

# ──────────────────────────────────────────────────────────────────────────────
def calc_dmg(atk, def_, crit_r, dodge_r):
    if random.randint(1, 100) <= dodge_r:
        return {'hit': False, 'crit': False, 'dmg': 0}
    crit = random.randint(1, 100) <= crit_r
    raw  = int(atk * 1.5) if crit else atk
    return {'hit': True, 'crit': crit, 'dmg': max(1, raw - def_)}

def init_ss():
    return {
        'round':             0,
        'thorns_acc':        0,
        'pierce_cd':         0,
        'corr':              0,      # 侵蝕層數（施加在敵方）
        'vengeance':         0,
        'undying_used':      False,
        'undying_immune':    False,
        'blood_crit_next':   False,
        'iw_healed':         False,
        'iw_heal_pending':   0,
    }

def round_start(arch, ss, p_hp, p_max, params):
    ss['round'] += 1
    heal = atk_bonus = reflect = 0

    if arch == 'vitality':
        heal = max(1, int(p_max * params['vitality']['heal_pct']))
        atk_bonus = params['vitality']['heal_atk_bonus']

    if arch == 'guardian':
        cd = params['guardian']['thorns_cd']
        if ss['round'] % cd == 0 and ss['thorns_acc'] > 0:
            reflect = int(ss['thorns_acc'] * params['guardian']['thorns_pct'])
            heal    = params['guardian']['thorns_heal']
            ss['thorns_acc'] = 0

    return heal, atk_bonus, reflect

def on_attack(arch, ss, hit, e_hp, e_max, corr_ref, params, e_base_def=0):
    true_dmg = 0
    if not hit['hit']:
        return true_dmg

    if arch == 'assault':
        p = params['assault']
        blood_base = e_max if p.get('blood_use_max_hp') else e_hp
        true_dmg += max(1, int(blood_base * p['blood_pct']))
        if p['blood_crit_bonus']:
            ss['blood_crit_next'] = True
        ss['pierce_cd'] += 1
        if ss['pierce_cd'] >= p['pierce_cd']:
            ss['pierce_cd'] = 0
            true_dmg += max(1, int(e_max * p['pierce_pct']))

    if arch == 'vitality':
        p = params['vitality']
        # ★比例型侵蝕：累積比例，真實削減 = floor(base_def × pct)
        corr_ref[0] = min(p['corr_cap'], corr_ref[0] + p['corr_per_hit'])
        if p['corr_true_dmg'] and e_base_def > 0:
            true_dmg += max(0, int(e_base_def * corr_ref[0] * p['corr_true_ratio']))

    return true_dmg

def on_take_dmg(arch, ss, dmg, crit, params, p_max_hp=0):
    if arch == 'guardian':
        if dmg > 0:
            # ★大傷害加乘：超過門檻的單次攻擊，荊棘累積更多
            threshold = int(p_max_hp * params['guardian']['thorns_threshold']) if p_max_hp > 0 else 0
            if threshold > 0 and dmg > threshold:
                ss['thorns_acc'] += int(dmg * params['guardian']['thorns_large_mult'])
            else:
                ss['thorns_acc'] += dmg
        if crit:
            ss['vengeance'] = max(ss['vengeance'], params['guardian']['vengeance'])

def get_eff_def(arch, ss, p_hp, p_max, base_def, params):
    if arch != 'guardian' or p_max <= 0:
        return base_def
    p = params['guardian']
    if (p_hp / p_max) < p['iw_threshold']:
        if not ss['iw_healed']:
            ss['iw_healed']       = True
            ss['iw_heal_pending'] = p['iw_heal']
        return int(base_def * p['iw_mult'])
    return base_def

def hunt_bonus(arch, e_hp, e_max, params):
    if arch != 'assault' or e_max <= 0:
        return 0.0
    p   = params['assault']
    pct = e_hp / e_max
    b   = 0.0
    if pct < p['hunt_threshold']:
        b += p['hunt_bonus_hi']
    if pct < 0.25:
        b += p['hunt_bonus_lo']
    return b

# ──────────────────────────────────────────────────────────────────────────────
def simulate_one(arch_a, arch_b, params):
    ca = {**CHARS[arch_a]}
    cb = {**CHARS[arch_b]}
    a  = {'hp': ca['hp'], 'max_hp': ca['hp'], 'atk': ca['atk'],
          'def': ca['def'], 'crit': ca['crit'], 'dodge': ca['dodge']}
    b  = {'hp': cb['hp'], 'max_hp': cb['hp'], 'atk': cb['atk'],
          'def': cb['def'], 'crit': cb['crit'], 'dodge': cb['dodge']}
    ssa, ssb = init_ss(), init_ss()
    corr_on_b = [0.0]   # vitality 對 b 的侵蝕比例（0.0~0.5）
    corr_on_a = [0.0]

    order = ['a', 'b'] if random.random() < 0.5 else ['b', 'a']

    for _ in range(200):   # 最多200回合防無限迴圈
        # ── 回合開始 ──
        heal_a, ab_a, ref_a = round_start(arch_a, ssa, a['hp'], a['max_hp'], params)
        if heal_a: a['hp'] = min(a['max_hp'], a['hp'] + heal_a)
        if ref_a:  b['hp'] -= ref_a

        heal_b, ab_b, ref_b = round_start(arch_b, ssb, b['hp'], b['max_hp'], params)
        if heal_b: b['hp'] = min(b['max_hp'], b['hp'] + heal_b)
        if ref_b:  a['hp'] -= ref_b

        if a['hp'] <= 0 or b['hp'] <= 0:
            break

        for turn in order:
            if a['hp'] <= 0 or b['hp'] <= 0:
                break
            if turn == 'a':
                atk, def_, ss_atk, ss_def, ab = a, b, ssa, ssb, ab_a
                atk_arch, def_arch = arch_a, arch_b
                corr_ref = corr_on_b
                corr_def = corr_on_a
            else:
                atk, def_, ss_atk, ss_def, ab = b, a, ssb, ssa, ab_b
                atk_arch, def_arch = arch_b, arch_a
                corr_ref = corr_on_a
                corr_def = corr_on_b

            # 暴擊率
            crit_r = atk['crit']
            if ss_atk['blood_crit_next']:
                crit_r = min(100, crit_r + 10)
                ss_atk['blood_crit_next'] = False
            if ss_atk['vengeance'] > 0:
                crit_r = 100
                ss_atk['vengeance'] = max(0, ss_atk['vengeance'] - 1)

            # 獵殺本能
            hb    = hunt_bonus(atk_arch, def_['hp'], def_['max_hp'], params)
            eff_a = int((atk['atk'] + ab) * (1 + hb))

            # 侵蝕比例型：計算 defender 的實際 DEF（base - floor(base × corr_pct)）
            base_def_val  = def_['def']
            corr_pct      = corr_def[0]
            corroded_def  = max(0, base_def_val - int(base_def_val * corr_pct))

            # 鋼鐵意志
            eff_d = get_eff_def(def_arch, ss_def,
                                 def_['hp'], def_['max_hp'], corroded_def, params)
            if ss_def['iw_heal_pending'] > 0:
                hw = ss_def['iw_heal_pending']
                ss_def['iw_heal_pending'] = 0
                def_['hp'] = min(def_['max_hp'], def_['hp'] + hw)

            hit = calc_dmg(eff_a, eff_d, crit_r, def_['dodge'])

            if hit['hit']:
                actual_dmg = hit['dmg']
                if ss_def['undying_immune'] and actual_dmg > 0:
                    ss_def['undying_immune'] = False
                    reduce = params['vitality'].get('revive_dmg_reduce', 1.0)
                    actual_dmg = max(1, int(actual_dmg * (1 - reduce)))
                def_['hp'] -= actual_dmg

                # 技能追加傷害（傳入 base_def_val 供侵蝕真傷計算）
                td = on_attack(atk_arch, ss_atk, hit, max(0, def_['hp']),
                               def_['max_hp'], corr_ref, params, base_def_val)
                def_['hp'] -= td

                # 荊棘累積（傳入 defender max_hp 供門檻判斷）
                on_take_dmg(def_arch, ss_def, actual_dmg, hit['crit'], params, def_['max_hp'])

                if (def_['hp'] <= 0 and def_arch == 'vitality'
                        and not ss_def['undying_used']):
                    ss_def['undying_used']   = True
                    ss_def['undying_immune'] = True
                    def_['hp'] = max(1, int(def_['max_hp']
                                           * params['vitality']['revive_pct']))
            else:
                on_take_dmg(def_arch, ss_def, 0, False, params, def_['max_hp'])

    return 'a' if a['hp'] > b['hp'] else ('b' if b['hp'] > a['hp'] else 'draw')

def winrate(arch_a, arch_b, params, n=8000):
    wins = sum(1 for _ in range(n)
               if simulate_one(arch_a, arch_b, params) == 'a')
    return wins / n

def run_all(params, n=8000):
    return {
        ('assault',  'guardian'): winrate('assault',  'guardian',  params, n),
        ('assault',  'vitality'): winrate('assault',  'vitality',  params, n),
        ('guardian', 'vitality'): winrate('guardian', 'vitality',  params, n),
    }

def ok(wr, lo=0.45, hi=0.55):
    return lo <= wr <= hi

# ── 調整邏輯 ─────────────────────────────────────────────────────────────────
def adjust(params, results):
    p = params  # shorthand
    ag = results[('assault',  'guardian')]
    av = results[('assault',  'vitality')]
    gv = results[('guardian', 'vitality')]

    # 攻擊 vs 防禦（防禦應贏攻擊）→ 目標 ag < 0.50
    if ag > 0.55:   # 攻擊太強 → 加強荊棘大傷加乘、削血肉渴望
        p['guardian']['thorns_large_mult'] = min(2.0, p['guardian']['thorns_large_mult'] + 0.05)
        p['assault']['blood_pct']          = max(0.005, p['assault']['blood_pct']        - 0.002)
    elif ag < 0.40: # 防禦太強 → 降荊棘加乘
        p['guardian']['thorns_large_mult'] = max(1.1, p['guardian']['thorns_large_mult'] - 0.05)
        p['assault']['blood_pct']          = min(0.040, p['assault']['blood_pct']        + 0.002)

    # 攻擊 vs 血量（攻擊應贏血量）→ 目標 av > 0.50
    if av < 0.45:   # 血量太強 → 加強血肉渴望、降生命脈動
        p['assault']['blood_pct']    = min(0.040, p['assault']['blood_pct']  + 0.003)
        p['vitality']['heal_pct']    = max(0.015, p['vitality']['heal_pct']  - 0.005)
    elif av > 0.60: # 攻擊太強 → 降血肉渴望、升生命脈動
        p['assault']['blood_pct']    = max(0.005, p['assault']['blood_pct']  - 0.003)
        p['vitality']['heal_pct']    = min(0.060, p['vitality']['heal_pct']  + 0.005)

    # 防禦 vs 血量（血量應贏防禦）→ 目標 gv < 0.50
    if gv > 0.55:   # 防禦太強 → 降鋼鐵意志、升侵蝕
        p['guardian']['iw_mult']         = max(1.1, p['guardian']['iw_mult']        - 0.08)
        p['vitality']['corr_per_hit']    = min(0.10, p['vitality']['corr_per_hit']  + 0.01)
    elif gv < 0.40: # 血量太強 → 升鋼鐵意志、降侵蝕
        p['guardian']['iw_mult']         = min(2.5, p['guardian']['iw_mult']        + 0.08)
        p['vitality']['corr_per_hit']    = max(0.02, p['vitality']['corr_per_hit']  - 0.01)

def fmt(wr):
    icon = "✅" if ok(wr) else "❌"
    return f"{icon} {wr*100:.1f}% / {(1-wr)*100:.1f}%"

# ── 主程式 ────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    params = copy.deepcopy(PARAMS)

    print("=" * 62)
    print("  PVP 三流派平衡模擬器（目標：各對戰勝率 45%~55%）")
    print("=" * 62)
    print(f"\n[初始模擬 8,000 場/對戰]\n")

    results = run_all(params, 8000)
    print(f"  攻擊 vs 防禦：{fmt(results[('assault','guardian')])}")
    print(f"  攻擊 vs 血量：{fmt(results[('assault','vitality')])}")
    print(f"  防禦 vs 血量：{fmt(results[('guardian','vitality')])}")

    if all(ok(v) for v in results.values()):
        print("\n✅ 初始數值已平衡！\n")
    else:
        print("\n[開始自動調整...]\n")
        for i in range(25):
            adjust(params, results)
            results = run_all(params, 5000)
            ag = results[('assault','guardian')]
            av = results[('assault','vitality')]
            gv = results[('guardian','vitality')]
            status = "✅" if all(ok(v) for v in results.values()) else "  "
            print(f"  Iter {i+1:02d} {status}  "
                  f"A>G={ag*100:5.1f}%  A>V={av*100:5.1f}%  G>V={gv*100:5.1f}%  "
                  f"blood={params['assault']['blood_pct']*100:.2f}%  "
                  f"thorns={params['guardian']['thorns_pct']*100:.0f}%  "
                  f"heal={params['vitality']['heal_pct']*100:.2f}%")
            if all(ok(v) for v in results.values()):
                print(f"\n  ✅ 第 {i+1} 輪收斂！")
                break

        print("\n[最終驗證 20,000 場/對戰]\n")
        results = run_all(params, 20000)

    # ── 最終報告 ──
    print("\n" + "=" * 62)
    print("  最終勝率")
    print("=" * 62)
    print(f"  攻擊 vs 防禦：{fmt(results[('assault','guardian')])}")
    print(f"  攻擊 vs 血量：{fmt(results[('assault','vitality')])}")
    print(f"  防禦 vs 血量：{fmt(results[('guardian','vitality')])}")

    p = params
    print("\n" + "=" * 62)
    print("  建議技能數值（需修改 lib/functions.php）")
    print("=" * 62)

    print("\n⚔️  攻擊流")
    print(f"  血肉渴望機制       : 吃敵方【最大HP】比例（原：當前HP）")
    print(f"  血肉渴望比例       : {p['assault']['blood_pct']*100:.2f}% 最大HP")
    print(f"  穿心一擊傷害       : {p['assault']['pierce_pct']*100:.1f}% 最大HP")
    print(f"  穿心冷卻           : 每 {p['assault']['pierce_cd']} 回合")
    print(f"  獵殺本能門檻       : HP < {p['assault']['hunt_threshold']*100:.0f}%")
    print(f"  獵殺加成 (>th)     : +{p['assault']['hunt_bonus_hi']*100:.0f}%")

    print("\n🛡️  防禦流")
    print(f"  荊棘反彈比例       : {p['guardian']['thorns_pct']*100:.0f}%")
    print(f"  荊棘爆發週期       : 每 {p['guardian']['thorns_cd']} 回合")
    print(f"  荊棘大傷門檻       : max_hp × {p['guardian']['thorns_threshold']*100:.0f}%")
    print(f"  荊棘大傷加乘       : ×{p['guardian']['thorns_large_mult']:.2f}")
    print(f"  鋼鐵意志觸發門檻   : HP < {p['guardian']['iw_threshold']*100:.0f}%")
    print(f"  鋼鐵意志防禦倍率   : x{p['guardian']['iw_mult']:.2f}")
    print(f"  報復之刃次數       : {p['guardian']['vengeance']} 次")

    print("\n💚  血量流")
    print(f"  生命脈動回復比例   : {p['vitality']['heal_pct']*100:.2f}% 最大HP")
    print(f"  侵蝕每次 +        : {p['vitality']['corr_per_hit']*100:.0f}% DEF削減比例")
    print(f"  侵蝕上限           : {p['vitality']['corr_cap']*100:.0f}% DEF削減")
    print(f"  侵蝕真傷倍率       : base_def × corr_pct × {p['vitality']['corr_true_ratio']:.2f}")
    print(f"  不滅復活HP比例     : {p['vitality']['revive_pct']*100:.0f}%")
    print(f"  不滅機制           : 復活後受傷減半")

    print("\n" + "=" * 62)
    unbal = [k for k, v in results.items() if not ok(v)]
    if not unbal:
        print("  ✅ 全部對戰已達平衡（45%~55%）")
    else:
        print("  ⚠️  以下對戰仍需手動微調：")
        names = {'assault':'攻擊','guardian':'防禦','vitality':'血量'}
        for a, b in unbal:
            print(f"     {names[a]} vs {names[b]}: {results[(a,b)]*100:.1f}%")
    print()
