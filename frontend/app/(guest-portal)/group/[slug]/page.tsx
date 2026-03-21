'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import WebsiteTemplate from '@/components/WebsiteTemplate';

export default function GroupPortalPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = React.use(params);
  
  const { data, isLoading } = useQuery({
    queryKey: ['public-group-website', slug],
    queryFn: () => api.get(`/api/v1/booking/group/${slug}`).then((res: any) => res.data),
  });

  if (isLoading) return <div className="min-h-screen bg-[#050810] flex items-center justify-center font-black tracking-widest text-primary animate-pulse">LOADING EXPERIENCE...</div>;
  if (!data) return <div className="min-h-screen bg-[#050810] flex items-center justify-center font-black text-white">PORTAL NOT FOUND</div>;

  const { group_website, branches } = data;

  return (
    <div className="min-h-screen bg-[#050810]">
      <WebsiteTemplate 
        settings={group_website.design_settings || {}}
        branding={{
          title: group_website.title,
          description: group_website.description,
          logo_url: group_website.logo_url,
          banner_url: group_website.banner_url,
          email: group_website.email,
          phone: group_website.phone,
          address: group_website.address
        }}
        branches={branches}
      />
    </div>
  );
}
