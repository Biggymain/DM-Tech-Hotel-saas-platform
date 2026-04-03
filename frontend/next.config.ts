import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  distDir: process.env.NEXT_DIST_DIR || ".next",
  trailingSlash: true,
  assetPrefix: '',
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
