# AGENTS.md — pi-dingding-proxy

## 项目概述

`pi-dingding-proxy` 是一个 PHP 代理服务，让钉钉群机器人与本机 PI Agent（`pi` CLI）交互：

- 监听 HTTP 端口，接收钉钉机器人回调（hook）消息；
- 校验钉钉签名、解析文本命令或普通提问；
- 返回 Markdown 格式的回复，通过消息中的 `sessionWebhook` 回发钉钉；
- 管理项目工作目录和 PI session，支持多轮连续对话。

仓库：https://github.com/jcleng/pi-dingding-proxy （公开，分支 `main`）

## 技术栈与环境

- 语言：PHP 8.2（静态二进制，见下方 Dockerfile）
- 运行：PHP 内置 Web Server（`php -S 0.0.0.0:7764 router.php`）
- PI Agent：`pi` CLI（Docker 镜像内置 node 24 + pi CLI；非容器运行时用本机 `pi`）
- 依赖：仅 PHP 扩展 `curl` / `json` / `mbstring`，无 Composer 依赖
- 测试：`php tests/run.php`（自写断言脚本，输出 `ok` 即通过）

## 目录结构

```text
├── Dockerfile              # Node 24 基础镜像 + 内置 pi CLI + 静态 PHP 8.2
├── docker-compose.yml      # 直接用已构建镜像；host 网络；挂 opencode_root/mywork/models+mc
├── router.php              # php -S 入口
├── public/index.php        # 回调处理主逻辑（签名校验、命令分发、回复）
├── src/
│   ├── Support.php         # 项目/session 列表读取、标题提取、history 解析
│   ├── DingTalk.php        # 钉钉签名校验与 webhook Markdown 回复
│   └── Proxy.php           # 命令路由、状态持久化、pi CLI 执行
├── tests/run.php           # 测试入口
├── docs/design.md          # 设计说明
└── var/state.json          # 运行时状态（已 gitignore）
```

## 核心数据来源

| 数据 | 默认路径 | 环境变量 |
|---|---|---|
| 项目注册表 | `/root/.pi-web/projects.json` | `PI_PROJECTS_FILE` |
| session 文件 | `/root/.pi/agent/sessions/<转义路径>/*.jsonl` | `PI_SESSION_DIR` |
| 用户状态 | `/root/.pi-dingding-proxy/state.json` | `PI_STATE_FILE` |
| PI 命令 | `pi` | `PI_BIN` |
| `/running` 活跃窗口 | 300 秒（文件 mtime 距今） | `PI_RUNNING_WINDOW` |

## 命令集（钉钉消息，支持 `@机器人` 前缀自动剥离）

```text
/help                        帮助（Markdown 列表）
/projects                    项目列表
/project <名称|路径>          切换项目（持久化状态）
/project new <名称>          提示先在服务器建目录
/sessions                    当前项目会话列表（含 40 字符标题、时间戳）
/session current             查看当前会话
/session <id前缀>            切换会话
/history [id]                最近 10 条对话（Markdown，去 thinking/toolCall/toolResult JSON）
/running                    查看窗口内（默认 300s）活跃的 session（文件 mtime）
（其他文本）                  作为 prompt 调用 pi -p --mode text，按当前 project/session 连续对话
```

## 关键实现约定

- **状态持久化**：每个钉钉 `senderStaffId` 一条记录（project/session），写入 `var/state.json`（临时文件 + `rename` 原子写）；HTTP 每次请求重建 `Proxy`，状态必须落盘。
- **PI 调用**：`proc_open` 参数数组（不经 shell），命令为
  `pi -p --mode text --no-extensions --no-mcp [--session <file>] <prompt>`，cwd 为项目路径，超时 `PI_TIMEOUT`（默认 600s）。
- **session JSONL 解析**：只提取 `text`/图片块，跳过 `thinking`、`toolCall`、`toolResult`；标题取第一个用户消息，超 40 字截断。
- **钉钉签名**：`base64(hmac_sha256(timestamp + "\n" + appSecret))`，与 header `sign` 比对；`DD_VERIFY_SIGNATURE=0` 可关闭。
- **回复**：Markdown 类型，`title` + `text`，POST 到回调携带的 `sessionWebhook`。
- **路径安全**：项目仅允许来自 `projects.json` 中已注册且目录存在的路径。
- **`/running`**：遍历 session 目录下所有 `*.jsonl`，按文件 mtime 距今是否小于窗口（`PI_RUNNING_WINDOW`，默认 300s）判定活跃，关联项目名与最后活动时间。

## Docker 部署

- compose 直接使用已构建镜像：`image: registry.cn-hangzhou.aliyuncs.com/jcleng/pi-dingding-proxy-latest`（`build:` 已移除；如需本地构建可用 `docker build .`）
- 基础镜像：`registry.cn-hangzhou.aliyuncs.com/jcleng/library-node:24`（Debian bookworm + Node 24，满足 pi engines >=22.19）
- PHP：静态二进制 `https://gh-proxy.com/https://github.com/jcleng/staticphpbuild/releases/download/static-php_8.2_20261007042314/php-8.2_20261007042314`（gh-proxy 加速；含 curl/mbstring/openssl/pcntl/posix，`proc_open` 可用）
- 网络：`network_mode: host`（宿主 7764 端口）
- 卷：`opencode_root:/root/`（external，实际名 `mywork_opencode_root`，与 pi agent 共享配置和会话）+ `/home/jcleng/work/mywork/:/home/jcleng/work/mywork/`（使 projects.json 里的路径可见）
- **镜像内置 node/pi**：基础镜像为 `registry.cn-hangzhou.aliyuncs.com/jcleng/library-node:24`（Debian bookworm + Node 24），构建时 `npm i -g @earendil-works/pi-coding-agent` 安装 `pi` CLI，并内置静态 PHP 8.2。容器内可直接执行 `pi -p --mode text --no-extensions --no-mcp [--session <file>] <prompt>`（cwd 为项目路径）。
- **配置来源**：`/root/.pi`（模型、认证、sessions）由 opencode_root 卷提供；但 `models.json`/`mcp.json` 在 opencode 容器里是 bind mount 覆盖的，因此代理容器同样需要挂载 `pi_models.json`、`pi_mcp.json` 到对应路径，否则模型列表为空。
- 本地构建需代理时：`docker build --network host --build-arg https_proxy=http://192.168.195.18:20171 --build-arg http_proxy=http://192.168.195.18:20171 .`

## CI 镜像构建（GitHub Actions）

使用 `jcleng/action-sync-images` 仓库的 [`build-repo.yml`](https://github.com/jcleng/action-sync-images/actions/workflows/build-repo.yml) workflow：从 Git 仓库 clone 指定分支 → `docker build` → 推送阿里云镜像仓库 → 创建 GitHub Release（附 `release.txt` 和 `Dockerfile`）。

触发方式（gh CLI，走代理）：

```bash
HTTPS_PROXY=http://192.168.195.18:20171 HTTP_PROXY=http://192.168.195.18:20171 gh workflow run build-repo.yml \
  -R jcleng/action-sync-images \
  -f arg_repo_url=https://github.com/jcleng/pi-dingding-proxy \
  -f arg_branch_name=main \
  -f arg_username=pi-dingding-proxy \
  -f arg_name=latest \
  -f arg_aliyunurl=registry.cn-hangzhou.aliyuncs.com \
  -f arg_aliyunuser=jcleng
```

构建结果（2026-10-09 验证）：

- Run：https://github.com/jcleng/action-sync-images/actions/runs/37916317354 （success）
- 镜像：`registry.cn-hangzhou.aliyuncs.com/jcleng/pi-dingding-proxy-latest:latest`
- Digest：`sha256:04f6e6f5dfd4c35bd833194a997ca6fbfc4b2c84769e41f94e1f348083573d9d`
- Release：https://github.com/jcleng/action-sync-images/releases/tag/pi-dingding-proxy-latest

同仓库其他 workflow：`sync-images-dockerHub-example..yml`（镜像 pull→push 同步，不构建）、`build-Dockerfile.yml`（按 Dockerfile 的 HTTP 地址构建）。

## 开发规范

- 改动后必须运行：`php tests/run.php` 和 `php -l src/*.php public/index.php router.php`，全绿才可提交。
- 遵循 TDD：先写失败测试再改实现。
- 提交信息用英文 `feat:` / `fix:` / `chore:` / `docs:` 前缀。
- 推送使用代理：`HTTPS_PROXY=http://192.168.195.18:20171 HTTP_PROXY=http://192.168.195.18:20171 git push`
- **不要提交运行时状态**：`var/state.json` 已在 `.gitignore`；钉钉密钥只放环境变量。
- 密钥参考（勿写入代码）：AppKey `dingmais7mxpfpavrqoy`，回调经 `timestamp`/`sign` header 校验。
