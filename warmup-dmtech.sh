#!/bin/bash
ROOT_DIR="/home/micky/DM-Tech-Hotel-saas-platform"

echo "🔥 Warming up DM-Tech Frontend Production Builds..."

build_port() {
    local APP_DIR=$1
    local PORT=$2
    
    echo "🏗️  Building $APP_DIR for Port $PORT..."
    cd "$ROOT_DIR/$APP_DIR" || exit
    
    # Remove old build artifacts to ensure a fresh standalone trace
    rm -rf ".next-$PORT"
    
    NEXT_TELEMETRY_DISABLED=1 \
    NEXT_DIST_DIR=".next-$PORT" \
    NEXT_PUBLIC_PORT=$PORT \
    NODE_OPTIONS="--max-old-space-size=1024" \
    npx next build
    
    cd "$ROOT_DIR" || exit
}

# 1. Build Frontends (3000-3003)
build_port "frontend" 3000
build_port "frontend" 3001
build_port "frontend" 3002
build_port "frontend" 3003

# 2. Build Guest-Apps (3004-3005)
build_port "guest-app" 3004
build_port "guest-app" 3005

echo "✅ All required production assets are ready!"
echo "➡️  You can now launch the cluster with: ./start-dmtech.sh"
