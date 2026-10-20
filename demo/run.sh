#!/bin/bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-podman}
NAME=${SOCA_DEMO_NAME:-soca-typo3-demo}
PORT=${SOCA_DEMO_PORT:-8092}
SECRETS=${SOCA_DEMO_SECRETS:-$(mktemp -d "${TMPDIR:-/tmp}/soca-typo3-XXXXXX")}
HOST=cms.soca.localhost
python3 "$ROOT/demo/init.py" typo3 "$SECRETS" "https://$HOST:$PORT"
"$ENGINE" build --network host -f "$ROOT/demo/Dockerfile" -t "$NAME:local" "$ROOT"
MOUNTS=()
for file in config.json client.pem state.key admin-password server.crt server.key; do
  MOUNTS+=(-v "$SECRETS/$file:/run/soca/$file:ro")
done
"$ENGINE" run -d --name "$NAME" -p "127.0.0.1:$PORT:80"   -e SOCA_CONFIG_FILE=/run/soca/config.json  "${MOUNTS[@]}" "$NAME:local"
printf 'Open https://%s:%s. Local account: localadmin. Password file: %s/admin-password
' "$HOST" "$PORT" "$SECRETS"
printf 'Trust the generated development certificate explicitly. CA/certificate: %s/server.crt
' "$SECRETS"
printf 'SSO starts disabled. See demo/README.md for candidate checks, recovery and cleanup.
'
