/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  images: {
    remotePatterns: [
      {
        protocol: 'http',
        hostname: 'localhost',
      },
      {
        protocol: 'http',
        hostname: '127.0.0.1',
      },
      {
        protocol: 'https',
        hostname: '**',
      },
    ],
  },
  async rewrites() {
    const phpApiBase = process.env.PHP_API_URL || 'http://localhost/athletica/api';
    return [
      {
        source: '/api/backend/:path*',
        destination: `${phpApiBase}/:path*`,
      },
    ];
  },
};

export default nextConfig;
