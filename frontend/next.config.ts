import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: 'standalone',
  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: process.env.NEXT_PUBLIC_API_URL 
          ? `${process.env.NEXT_PUBLIC_API_URL}/:path*` 
          : 'http://127.0.0.1:8000/api/:path*',
      },
      {
        source: '/sanctum/:path*',
        destination: 'http://127.0.0.1:8000/sanctum/:path*',
      }
    ];
  },
};

export default nextConfig;
