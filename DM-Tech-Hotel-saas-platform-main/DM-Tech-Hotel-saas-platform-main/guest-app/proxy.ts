import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function proxy(request: NextRequest) {
  const host = request.headers.get('host') || '';
  const port = host.split(':')[1] || request.nextUrl.port || '80';
  const { pathname } = request.nextUrl;

  // Strict Port-to-Role Gateway Logic for Guest App
  if (pathname === '/') {
    switch (port) {
      case '3004':
        // Guest - Redirect to the Menu/Ordering interface
        return NextResponse.redirect(new URL('/menu', request.url));
      
      case '3005':
        // Booking - Redirect to the Room Booking engine
        return NextResponse.redirect(new URL('/booking', request.url));
        
      default:
        return NextResponse.next();
    }
  }

  // Optional: Enforce isolation if you have specific paths for 3004 vs 3005
  if (port === '3004' && pathname.startsWith('/booking')) {
      return NextResponse.redirect(new URL('/menu', request.url));
  }
  if (port === '3005' && pathname.startsWith('/menu')) {
      return NextResponse.redirect(new URL('/booking', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|favicon.ico).*)',
  ],
};
