# YandH

YandH 是一款情侣专属的私密社交 App，集动态分享、日记、即时通讯、小游戏和 AI 助手于一体。

## 功能

| 模块    | 说明                                          |
| ----- | ------------------------------------------- |
| 动态广场  | 发布图文动态，评论互动，Emoji 表态                        |
| 私密聊天  | 一对一实时消息，消息撤回                                |
| 共享日记  | 情侣日记本，支持编辑与详情查看                             |
| 纪念日   | 纪念日倒计时，定时推送提醒                               |
| AI 对话 | 接入 AI 大模型，智能聊天                              |
| 小游戏   | 五子棋（标准版 / Cracked 版）、你射我猜、你画我猜、拼豆           |
| 个人中心  | 头像上传、昵称修改、个人资料编辑                            |
| 暗黑模式  | 全局暗色主题切换                                    |
| 推送通知  | UniPush 2.0 消息推送（聊天、互动、纪念日提醒） |

## 技术栈

- **前端**：Vue 3（Composition API + `<script setup>`）+ Vue Router（Hash 模式）+ Vite
- **后端 API**：PHP（登录注册、动态、聊天、日记、游戏、推送等）
- **移动端运行**：HBuilder / 5+ App Runtime
- **依赖库**：marked（Markdown 渲染）、highlight.js（代码高亮）、tesseract.js（OCR 识别）

## 项目结构

```
reBuild/
├── src/
│   ├── views/          # 页面组件
│   ├── components/     # 公共组件（导航栏、底部菜单等）
│   ├── router/         # 路由配置（Hash 模式）
│   ├── utils/          # 工具函数（推送、日历、导航动效等）
│   ├── assets/         # 静态资源与样式
│   ├── App.vue         # 根组件
│   └── main.js         # 入口文件
├── api/                # PHP 后端接口
├── public/             # 公共静态资源
├── index.html          # HTML 入口
├── vite.config.js      # Vite 构建配置
└── package.json
```

## 快速开始

### 1. 安装依赖

```bash
npm install
```

### 2. 本地开发

```bash
npm run dev
```

启动后浏览器访问 Vite 输出的本地地址（默认 `http://localhost:5173`）即可预览。

### 3. 构建生产包

```bash
npm run build
```

构建产物在 `dist/` 目录下。

### 4. 部署

#### HBuilder 真机打包

`vite.config.js` 中 `base` 已设置为 `'./'`（相对路径），可直接将 `dist/` 目录导入 HBuilder，打包为 5+ App 运行。

#### Web 服务器部署

若部署到网站子目录（如 `/YandH/rebuild/dist/`），需将 `vite.config.js` 中的 `base` 改为对应路径后重新构建：

```js
base: '/YandH/rebuild/dist/'
```

### 5. 后端配置

将 `api/` 目录部署到 PHP 服务器（如 Apache / Nginx + PHP），并修改 `src/utils/config.js` 中的 API 地址指向你的后端。

## 页面路由

| 路由                | 页面            | 说明             |
| ----------------- | ------------- | -------------- |
| `/`               | Login         | 登录页            |
| `/home`           | Home          | 动态广场首页         |
| `/interaction`    | Interaction   | 互动消息           |
| `/anniversary`    | Anniversary   | 纪念日            |
| `/profile`        | Profile       | 个人中心           |
| `/ai`             | AiPage        | AI 对话          |
| `/post`           | Post          | 发布动态           |
| `/detail`         | Detail        | 动态详情           |
| `/diary`          | Diary         | 日记列表           |
| `/diary/detail`   | DiaryDetail   | 日记详情           |
| `/diary/edit`     | DiaryEdit     | 写日记            |
| `/userlist`       | UserList      | 用户列表           |
| `/chat`           | Chat          | 私密聊天           |
| `/gomoku`         | Gomoku        | 五子棋（标准版）       |
| `/gomoku-cracked` | GomokuCracked | 五子棋（Cracked 版） |
| `/shoot-guess`    | ShootGuess    | 你射我猜           |
| `/draw-guess`     | DrawGuess     | 你画我猜           |
| `/pindou`         | Pindou        | 拼豆游戏           |

## 自定义配置

- **API 地址**：修改 `src/utils/config.js`
- **构建路径**：修改 `vite.config.js` 中的 `base`
- **应用名称**：修改 `index.html` 中的 `<title>`
- **推送服务**：在 `src/utils/push.js` 中配置 UniPush 参数
