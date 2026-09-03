import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  // Extract port from either nextUrl or Host header (Host header is more reliable in built apps locally)
  const host = request.headers.get('host') || '';
  const port = host.split(':')[1] || request.nextUrl.port || '80'; // default 80 if no port
  const { pathname } = request.nextUrl;

  // Strict Port-to-Role Gateway Logic
  if (pathname === '/') {
    switch (port) {
      case '3000':
        // Super Admin - Redirect to the unified login
        return NextResponse.redirect(new URL('/login', request.url));
      
      case '3001':
        // Main Hub - ONLY port allowed to show the Landing Page
        return NextResponse.next();
      
      case '3002':
        // Branch Manager - Redirect to the unified login
        return NextResponse.redirect(new URL('/login', request.url));
      
      case '3003':
        // Staff Ops - Redirect to /staff/pin
        return NextResponse.redirect(new URL('/staff/pin', request.url));
        
      default:
        // By default, if it's matching these ports but none hit, allow it or redirect.
        // We'll allow it to pass through to be safe if not one of our targeted ports (e.g., standard 80/443 mapping)
        return NextResponse.next();
    }
  }

  // Enforce zero-cross-access on paths globally across the ports
  // If someone tries to hit /admin/login from port 3002, kick them back
  if (port === '3002' && pathname.startsWith('/admin')) {
      return NextResponse.redirect(new URL('/manager/login', request.url));
  }
  if (port === '3000' && pathname.startsWith('/manager')) {
      return NextResponse.redirect(new URL('/admin/login', request.url));
  }

  return NextResponse.next();
}

// Config to ensure middleware only runs on specific paths like the root and specific sub-routes, 
// or simply run on all to catch cross-port bleeding.
export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     */
    '/((?!api|_next/static|_next/image|favicon.ico).*)',
  ],
};
