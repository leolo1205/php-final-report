# UI 全站重設計 — 設計文件

**日期：** 2026-06-17
**專案：** 塔城傳說 v1.6 裝備鍛造
**範圍：** 前台所有頁面 + 後台 admin 頁面

---

## 目標

將全站 UI 統一為以 `forge.php` 為基準的設計語言，解決目前各頁面樣式不一致、inline style 難以維護的問題。

---

## 檔案結構

```
版本1.6裝備鍛造/
├── assets/
│   ├── style.css        ← 全站共用樣式（新增）
│   └── admin.css        ← 後台專屬補充樣式（新增）
├── admin/               ← 現有後台頁面，引入 ../assets/style.css + ../assets/admin.css
├── forge.php            ← 移除 <style>，改引入 assets/style.css
├── index.php            ← 同上
├── login.php            ← 同上
├── register.php         ← 同上
├── arena.php            ← 同上
├── skills.php           ← 同上
├── skills_build.php     ← 同上
├── tower.php            ← 同上
├── tower_combat.php     ← 同上
├── tower_events.php     ← 同上
├── tower_monsters.php   ← 同上
└── tower_story.php      ← 同上
```

每個前台 PHP 頁面 `<head>` 統一引入：
```html
<link rel="stylesheet" href="assets/style.css">
```

後台 admin 頁面額外加：
```html
<link rel="stylesheet" href="../assets/admin.css">
```

---

## 設計 Token（CSS 變數）

定義於 `style.css` 頂部的 `:root`，以 `forge.php` 現有配色為基準：

```css
:root {
  /* 背景層次 */
  --bg-base:   #0d0d1a;
  --bg-card:   #16213e;
  --bg-panel:  #1a1a2e;

  /* 強調色 */
  --accent:        #ffca28;
  --accent-blue:   #4fc3f7;
  --accent-red:    #ef5350;
  --accent-green:  #66bb6a;

  /* 文字 */
  --text-primary:   #e0e0e0;
  --text-muted:     #94a3b8;
  --text-dim:       #64748b;

  /* 邊框 */
  --border:         #2a2a4a;
  --border-hover:   #4fc3f7;
}
```

---

## 共用元件（style.css）

### 基礎重置
```css
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Segoe UI', '微軟正黑體', sans-serif;
  background: var(--bg-base);
  color: var(--text-primary);
}
```

### 左側邊欄（`.sidebar`）
- 桌機：固定左側，寬 220px
- 手機（≤760px）：變成底部 tab bar，高 64px
- 包含：logo 區、導覽連結、玩家資訊、登出按鈕
- 配色改用設計 token（移除 `index.php` 的 hardcoded 色值）
- `.sidebar-link.active` 使用 `var(--accent-blue)` 左邊框高亮

### 卡片（`.card`）
- `background: var(--bg-card)`
- `border: 1px solid var(--border)`
- `border-radius: 16px`
- `padding: 34px 36px`
- `box-shadow: 0 12px 28px rgba(0,0,0,.28)`

### 按鈕
| Class | 用途 | 樣式 |
|---|---|---|
| `.btn-primary` | 主要行動（強化、出發等） | 金色漸層，深色文字 |
| `.btn-outline` | 次要行動（返回、取消） | 透明底，灰邊框 |
| `.btn-danger` | 危險操作（重置帳號） | 透明底，紅邊框，hover 變紅底 |

### 進度條（`.bar-track` / `.bar-fill`）
- 直接從 `forge.php` 搬，無修改

### 資訊格子（`.info-grid` / `.info-card`）
- 直接從 `forge.php` 搬，通用化

### Toast 通知（`#toast`）
- 直接從 `forge.php` 搬
- 三種狀態：`.ok`（綠）、`.err`（紅）、`.info`（灰）

### 表單元素
- `input[type=text/password]`：暗色底 `var(--bg-base)`，邊框 `var(--border)`，focus 時邊框 `var(--accent-blue)`
- `label`：`var(--text-muted)`，12px

### RWD 斷點
- `≤760px`：側邊欄→底部 tab bar，`.info-grid` 從 4 欄→2 欄
- `≥761px`：`body` 左 padding 240px（避開側邊欄）

---

## 後台補充樣式（admin.css）

共用 `style.css` 的設計 token 和基礎元件，額外定義：

- `.admin-sidebar`：比前台側邊欄更緊湊，加入 section 分組（使用者管理、系統等）
- `.data-table`：後台表格，含 hover 高亮、響應式橫向捲動
- `.badge`：狀態標籤，小圓角色塊（.badge-green / .badge-red / .badge-gray）
- `.admin-topbar`：後台頂部資訊欄（顯示管理員身份）

---

## 各頁面改動範圍

| 頁面 | 改動 |
|---|---|
| `forge.php` | 移除 `<style>`，引入 `style.css`，class 名稱對齊新規範 |
| `index.php` | 移除 `<style>` 內所有樣式，sidebar 改用 `.sidebar`，panels 改用 `.card` |
| `login.php` | 移除 inline style，套用表單元素樣式；**無側邊欄**，置中卡片佈局 |
| `register.php` | 同 login.php；**無側邊欄** |
| `arena.php` | 移除 inline style，套用 `.card`、`.btn-primary` |
| `skills.php` | 同上 |
| `skills_build.php` | 同上 |
| `tower.php` | 同上，含側邊欄 |
| `tower_combat.php` | 戰鬥畫面，套用 `.card`、進度條樣式；側邊欄可省略（沉浸式頁面） |
| `tower_events.php` | 套用 `.card`、`.btn-primary`、`.btn-outline` |
| `tower_monsters.php` | 同 tower_events.php |
| `tower_story.php` | 同 tower_events.php |
| `admin/*.php` | 移除 inline style，引入 `style.css` + `admin.css` |

---

## 不在此次範圍內

- 修改 PHP 後端邏輯
- 新增任何功能
- 修改 JS 互動行為（除非與樣式 class 名稱有關）
