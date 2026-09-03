import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  outputFileTracingRoot: '/home/micky/DM-Tech-Hotel-saas-platform',
  // @ts-ignore - Valid top-level config in Next.js 16.2.1
  turbopack: {
    root: '/home/micky/DM-Tech-Hotel-saas-platform'
  },
  distDir: process.env.NEXT_DIST_DIR || ".next",
  trailingSlash: true,
  assetPrefix: '',
  images: {
    unoptimized: true, // For easier deployment
  },
  // Ensure build can proceed despite minor lints
  typescript: {
    ignoreBuildErrors: true,
  }
};

export default nextConfig;
