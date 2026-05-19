// ── 共用工具 ──────────────────────────────────────────────────
const TRAIN_CD_MS = 4 * 60 * 60 * 1000; // 4 小時（demo 可改小）
// const TRAIN_CD_MS = 30 * 1000; // demo 用 30 秒

function saveState(key, val) { localStorage.setItem(key, JSON.stringify(val)); }
function loadState(key)       { try { return JSON.parse(localStorage.getItem(key)); } catch { return null; } }

function showToast(msg, duration = 2200) {
  let t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; t.className = 'toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

function fmtTime(ms) {
  if (ms <= 0) return '可以訓練';
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  return h > 0 ? `${h}h ${m}m` : `${m}m ${s}s`;
}

// ── 預設角色模板 ──────────────────────────────────────────────
function newCharacter(name) {
  return {
    name,
    level: 1, exp: 0, expMax: 100,
    hp: 120, maxHp: 120,
    str: 10, agi: 10, vit: 9, wis: 8, per: 8, cha: 8,
    towerFloor: 1,
    lastTrained: null,
    lastDelta: { str:0, agi:0, vit:0, wis:0, per:0, cha:0 },
  };
}

// ── 訓練邏輯 ─────────────────────────────────────────────────
function doTrain(char) {
  const now = Date.now();
  const elapsed = char.lastTrained ? now - char.lastTrained : TRAIN_CD_MS;
  if (elapsed < TRAIN_CD_MS) return { ok: false, remaining: TRAIN_CD_MS - elapsed };

  const stats = ['str','agi','vit','wis','per','cha'];
  const delta = { str:0, agi:0, vit:0, wis:0, per:0, cha:0 };
  // 隨機挑 2 個主要屬性 +2~4，1 個副屬性 +1~2
  const shuffled = [...stats].sort(() => Math.random() - .5);
  delta[shuffled[0]] = rand(2, 4);
  delta[shuffled[1]] = rand(2, 4);
  delta[shuffled[2]] = rand(1, 2);

  stats.forEach(s => char[s] += delta[s]);
  char.maxHp = 80 + char.vit * 5;
  char.hp = Math.min(char.hp + 20, char.maxHp);

  const expGain = rand(20, 40);
  char.exp += expGain;
  let leveledUp = false;
  while (char.exp >= char.expMax) {
    char.exp -= char.expMax;
    char.level++;
    char.expMax = char.level * 100;
    stats.forEach(s => char[s] += 1);
    char.maxHp = 80 + char.vit * 5;
    char.hp = char.maxHp;
    leveledUp = true;
  }

  char.lastTrained = now;
  char.lastDelta = delta;
  return { ok: true, expGain, leveledUp, delta };
}

function rand(a, b) { return Math.floor(Math.random() * (b - a + 1)) + a; }

// ── 塔層怪物生成 ──────────────────────────────────────────────
const MONSTER_NAMES = [
  '石像哥布林','暗影蜘蛛','熔岩蟹','毒霧骷髏','狂怒獸人',
  '幽靈騎士','深海觸手','鐵甲巨蟻','瘟疫蝙蝠','冰晶元素',
];
const BOSS_NAMES = [
  '???','熔焰龍將','深淵守門者','冰霜女王','混沌裂縫者',
  '虛空吞噬者','鋼鐵傀儡王','死亡預言者','暗影教主','神域守護者',
];

function getMonster(floor) {
  const isBoss = floor % 10 === 0;
  const base = { hp: 50 + floor*15, atk: 5 + floor*2, def: 2 + floor, spd: 4 + Math.floor(floor/5) };
  if (isBoss) {
    const bossIdx = Math.floor(floor / 10) - 1;
    return {
      name: BOSS_NAMES[Math.min(bossIdx, BOSS_NAMES.length-1)],
      hp: base.hp * 3, maxHp: base.hp * 3,
      atk: base.atk * 2, def: base.def * 2, spd: base.spd + 3,
      isBoss: true, expReward: (30 + floor*5) * 5,
    };
  }
  const idx = (floor - 1) % MONSTER_NAMES.length;
  return {
    name: MONSTER_NAMES[idx],
    hp: base.hp, maxHp: base.hp,
    atk: base.atk, def: base.def, spd: base.spd,
    isBoss: false, expReward: 30 + floor*5,
  };
}

// ── 戰鬥計算 ─────────────────────────────────────────────────
function calcDamage(atk, def, per = 0) {
  const crit = Math.random() < (0.05 + per * 0.008);
  let dmg = Math.max(1, atk - def + rand(-2, 2));
  if (crit) dmg = Math.floor(dmg * 1.5);
  return { dmg, crit };
}

function dodgeChance(agi) { return 0.03 + agi * 0.005; }
