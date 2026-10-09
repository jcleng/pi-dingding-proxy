# pi-dingding-proxy

PHP bridge between DingTalk chatbot and local PI agent.

## Run

```bash
DD_APP_SECRET='your-secret' DD_VERIFY_SIGNATURE=1 \
php -S 0.0.0.0:7764 router.php
```

DingTalk callback URL: `http://host:7764/`.

Environment: `DD_PORT`, `DD_APP_SECRET`, `DD_VERIFY_SIGNATURE`, `PI_BIN`, `PI_PROJECTS_FILE`, `PI_SESSION_DIR`, `PI_TIMEOUT`.

Commands: `/help`, `/projects`, `/project <name|path>`, `/sessions`, `/session current`, `/session <id>`, `/history`. Select a project then send ordinary text for a PI prompt. State is maintained per DingTalk sender during the process lifetime.

For production use PHP-FPM or a process manager, HTTPS/reverse proxy, and a persistent state store. Do not commit DingTalk secrets.

## Test

```bash
php tests/run.php
```
