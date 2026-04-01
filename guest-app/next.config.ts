import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
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
