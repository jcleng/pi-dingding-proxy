# pi-dingding-proxy

PHP bridge between DingTalk chatbot and local PI agent.

## Run

```bash
DD_APP_SECRET='your-secret' DD_VERIFY_SIGNATURE=1 \
php -S 0.0.0.0:7764 router.php
```

DingTalk callback URL: `http://host:7764/`.

Environment: `DD_PORT`, `DD_APP_SECRET`, `DD_VERIFY_SIGNATURE`, `PI_BIN`, `PI_PROJECTS_FILE`, `PI_SESSION_DIR`, `PI_TIMEOUT`, `PI_STATE_FILE`.

Commands: `/help`, `/projects`, `/project <name|path>`, `/sessions`, `/session current`, `/session <id>`, `/history`. Select a project then send ordinary text for a PI prompt. State is maintained per DingTalk sender in `var/state.json`, so project/session selection survives requests and service restarts. Group-message robot mentions are stripped before command parsing.

For production use PHP-FPM or a process manager, HTTPS/reverse proxy, and a persistent state store. Do not commit DingTalk secrets.

## Test

```bash
php tests/run.php
```

## Docker

```bash
DD_APP_SECRET='your-secret' docker compose up -d --build
```

`docker-compose.yml` uses `network_mode: host` (port 7764), the Alpine base image, the static PHP 8.2 binary, and mounts the shared `opencode_root` volume plus `/home/jcleng/work/mywork/`. The PHP proxy expects `pi` to be reachable as `PI_BIN` inside the container.
