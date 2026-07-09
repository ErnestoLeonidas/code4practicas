#!/usr/bin/env bash
set -euo pipefail

# Script para iniciar el backend PHP en el puerto 18081
# y luego arrancar Vite (frontend) en 51731.

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
API_DIR="$ROOT_DIR/api"
FRONTEND_DIR="$ROOT_DIR/frontend"

BACKEND_PORT=18081
FRONTEND_PORT=51731

echo "Starting PHP dev server on http://localhost:${BACKEND_PORT} (api/)"
pushd "$API_DIR" >/dev/null
php -S localhost:${BACKEND_PORT} -t . &
PHP_PID=$!
popd >/dev/null

echo "PHP server PID: ${PHP_PID}"
echo "Starting Vite dev server in frontend/ (port ${FRONTEND_PORT})"
pushd "$FRONTEND_DIR" >/dev/null
npm run dev
popd >/dev/null

echo "Dev servers stopped. (Vite exited)"
