#!/bin/bash
ROOT_DIR="/home/micky/DM-Tech-Hotel-saas-platform"

echo "🚀 Launching DM-Tech Digital Fortress (Native Environment Isolation Mode)..."

echo "Which Port are you actively coding on? (3000-3005 or NONE)"
read -r ACTIVE_PORT

# 1. Start Backend
cd "$ROOT_DIR/backend" && php artisan serve --port=8000 --no-reload & 

# Function to setup and launch a port natively
launch_port() {
    local APP_DIR=$1
    local PORT=$2
    
    # The Magic Fix: Isolate the TMPDIR to bypass Turbopack's IPC Socket locks
    local ISOLATION_DIR="/tmp/dmtech-isolation/$PORT"
    mkdir -p "$ISOLATION_DIR"
    
    # We pass NEXT_DIST_DIR which successfully binds because we updated next.config.ts!
    if [ "$PORT" = "$ACTIVE_PORT" ]; then
        echo "▶️ 🔥 Booting $APP_DIR on Port $PORT (ACTIVE DEV MODE - Isolated Socket & Cache)..."
        cd "$ROOT_DIR/$APP_DIR" && \
        TMPDIR="$ISOLATION_DIR" \
        NEXT_DIST_DIR=".next-$PORT" \
        NEXT_PUBLIC_PORT=$PORT \
        PORT=$PORT \
        __NEXT_PRIVATE_PREBUNDLED_REACT=1 \
        NEXT_PRIVATE_WORKER=1 \
        NODE_OPTIONS="--title=dm-tech-port-$PORT --max-old-space-size=1024" \
        npx next dev -p $PORT &
    else
        echo "▶️ 🧊 Booting $APP_DIR on Port $PORT (PRODUCTION MODE - Isolated Socket & Cache)..."
        cd "$ROOT_DIR/$APP_DIR" && \
        TMPDIR="$ISOLATION_DIR" \
        
        # Check for missing manifests to prevent ENOENT errors
        if [ ! -f ".next-$PORT/prerender-manifest.json" ]; then
            echo "   (Missing Build Manifests - Executing Clean Build...)"
            NEXT_DIST_DIR=".next-$PORT" npx next build
        fi

        # Detect standalone build (with workspace root optimization applied)
        if [ -f ".next-$PORT/standalone/$APP_DIR/server.js" ]; then
            echo "   (Detected Standalone Build - Launching via Node...)"
            # Copy public and static to standalone for correct asset resolution
            cp -r "public" ".next-$PORT/standalone/$APP_DIR/public" 2>/dev/null
            cp -r ".next-$PORT/static" ".next-$PORT/standalone/$APP_DIR/.next-$PORT/static" 2>/dev/null
            
            PORT=$PORT \
            HOSTNAME="0.0.0.0" \
            NODE_OPTIONS="--title=dm-tech-port-$PORT --max-old-space-size=1024" \
            node ".next-$PORT/standalone/$APP_DIR/server.js" &
        else
            echo "   (Standard Build - Launching via Next Start...)"
            NEXT_DIST_DIR=".next-$PORT" \
            PORT=$PORT \
            __NEXT_PRIVATE_PREBUNDLED_REACT=1 \
            NEXT_PRIVATE_WORKER=1 \
            NEXT_TELEMETRY_DISABLED=1 \
            NODE_OPTIONS="--title=dm-tech-port-$PORT --max-old-space-size=1024" \
            npx next start -p $PORT &
        fi
    fi
}

# 2. Launch Frontends (3000-3003)
launch_port "frontend" 3000
launch_port "frontend" 3001
launch_port "frontend" 3002
launch_port "frontend" 3003

# 3. Launch Guest-Apps (3004-3005)
launch_port "guest-app" 3004
launch_port "guest-app" 3005

echo "✅ 6-Port Native Isolation active. No sudo required!"

# Cleanup on exit
cleanup() {
    echo ""
    echo "🧹 Teardown initiated. Purging isolate footprints..."
    pkill -P $$ 
    wait 2>/dev/null
    rm -rf /tmp/dmtech-isolation
    echo "✅ Shutdown complete."
    exit 0
}

trap cleanup INT TERM
wait
