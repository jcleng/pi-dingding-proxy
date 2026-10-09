# pi-dingding-proxy 设计

PHP CLI HTTP 服务，接收钉钉机器人回调并同步调用本机 `pi -p --mode text`。项目注册从 PI Web 的 `projects.json` 读取，可通过 `PI_PROJECTS_FILE` 覆盖；会话从项目 session 目录读取。每个钉钉发送者保存当前项目/session 映射，实现连续对话。

命令：`/help`、`/projects`、`/project <名称|路径>`、`/project new <名称>`、`/sessions`、`/session current`、`/session <id>`、`/history [id]`，其余文本作为 prompt。钉钉签名校验可配置为强制/关闭；Webhook 回复 Markdown。PI 命令通过 proc_open、参数数组和项目 cwd 执行，避免 shell 注入。HTTP 入口同时提供 `/health`。

配置通过环境变量：`DD_PORT`、`DD_HOST`、`DD_APP_SECRET`、`DD_VERIFY_SIGNATURE`、`PI_BIN`、`PI_PROJECTS_FILE`、`PI_SESSION_DIR`、`PI_PROJECT_ROOT`、`PI_TIMEOUT`。
