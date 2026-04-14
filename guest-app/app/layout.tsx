import type { Metadata, Viewport } from "next";
import "./globals.css";
import Providers from "./providers";
import { Toaster } from "@/components/ui/sonner";

export const metadata: Metadata = {
  title: "Guest Portal | DM Tech Hotel",
  description: "Experience premium hotel services at your fingertips.",
  manifest: "/manifest.json",
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: "DM Tech Guest",
  },
};

export const viewport: Viewport = {
  themeColor: "#0f172a",
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  userScalable: false,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className="dark">
      <body
        className="font-sans antialiased bg-stone-50 text-slate-900 min-h-screen selection:bg-slate-950/10"
      >
        <Providers>
          <div className="relative flex min-h-screen flex-col overflow-x-hidden">
            <main className="flex-1 w-full z-10">
              {children}
            </main>
          </div>
          <Toaster position="top-center" />
        </Providers>
      </body>
    </html>
  );
}
