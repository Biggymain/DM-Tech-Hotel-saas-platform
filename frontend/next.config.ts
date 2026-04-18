import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  // Dynamic root detection for Kali, Docker, and GitHub Actions
  outputFileTracingRoot: process.cwd(),

  // @ts-ignore - Required for specific build optimizations in v16
  turbopack: {
    root: process.cwd()
  },

  distDir: process.env.NEXT_DIST_DIR || ".next",
  trailingSlash: true,
  images: {
    unoptimized: true,
  },
};

export default nextConfig;