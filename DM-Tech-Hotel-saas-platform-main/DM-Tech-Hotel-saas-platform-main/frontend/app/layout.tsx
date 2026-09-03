import type { Metadata } from 'next';
import './globals.css';
import { ThemeProvider } from '@/context/ThemeProvider';
import QueryProvider from '@/context/QueryProvider';
import { AuthProvider } from '@/context/AuthProvider';
import { UIProvider } from '@/context/UIContext';
import { Toaster } from '@/components/ui/sonner';

const geistSans = { variable: 'font-sans' };
const geistMono = { variable: 'font-mono' };

export const metadata: Metadata = {
  title: 'Admin GUI | SaaS Dashboard',
  description: 'Enterprise operational dashboard for hotel management.',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={`${geistSans.variable} ${geistMono.variable} antialiased`}>
        <ThemeProvider
          attribute="class"
          defaultTheme="system"
          enableSystem
          disableTransitionOnChange
        >
          <QueryProvider>
            <AuthProvider>
              <UIProvider>
                {children}
                <Toaster />
              </UIProvider>
            </AuthProvider>
          </QueryProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
