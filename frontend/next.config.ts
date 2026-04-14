import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  outputFileTracingRoot: '/home/micky/DM-Tech-Hotel-saas-platform',
  // @ts-ignore - Valid top-level config in Next.js 16.2.1
  turbopack: {
    root: '/home/micky/DM-Tech-Hotel-saas-platform'
  },
  experimental: {
  },
  distDir: process.env.NEXT_DIST_DIR || ".next",
  trailingSlash: true,
  assetPrefix: '',
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
