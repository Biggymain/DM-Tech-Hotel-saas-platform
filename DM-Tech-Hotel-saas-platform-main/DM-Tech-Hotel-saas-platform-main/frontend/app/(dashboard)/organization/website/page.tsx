'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { 
  Globe, 
  MapPin, 
  Image as ImageIcon, 
  Sparkles, 
  Save, 
  ExternalLink,
  Plus,
  Trash2,
  ChevronRight,
  Palette
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from 'sonner';
import api from '@/lib/api';
import { ImageUpload } from '@/components/ImageUpload';
import { DesignSystemEditor } from '@/components/DesignSystemEditor';
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger 
} from '@/components/ui/dialog';

export default function WebsiteCreatorPage() {
  const [loading, setLoading] = React.useState(true);
  const [saving, setSaving] = React.useState(false);
  const [website, setWebsite] = React.useState<any>(null);
  const [branches, setBranches] = React.useState<any[]>([]);
  const [activeAiField, setActiveAiField] = React.useState<string | null>(null);

  React.useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setLoading(true);
      const { data } = await api.get('/api/v1/organization/website');
      
      // Merge with defaults to ensure all fields exist
      const enrichedData = {
        ...data,
        email: data.email || '',
        phone: data.phone || '',
        address: data.address || '',
        design_settings: {
          hero_alignment: 'left',
          font_family: 'sans',
          font_weight: 'normal',
          card_style: 'glass',
          button_style: 'rounded',
          primary_color: '#4f46e5',
          secondary_color: '#7c3aed',
          accent_color: '#f59e0b',
          ...(data.design_settings || {})
        }
      };
      
      setWebsite(enrichedData);
      setBranches(data.group?.branches || []);
    } catch (e) {
      console.error("Failed to fetch website data", e);
      setWebsite({
        title: '',
        slug: '',
        description: '',
        about_text: '',
        email: '',
        phone: '',
        address: '',
        primary_color: '#4f46e5',
        secondary_color: '#7c3aed',
        features: [],
        social_links: { facebook: '', twitter: '', instagram: '' },
        design_settings: {
          hero_alignment: 'left',
          font_family: 'sans',
          font_weight: 'normal',
          card_style: 'glass',
          button_style: 'rounded',
          primary_color: '#4f46e5',
          secondary_color: '#7c3aed',
          accent_color: '#f59e0b',
        }
      });
    } finally {
      setLoading(false);
    }
  };

  const handleSaveGroup = async () => {
    try {
      setSaving(true);
      await api.put('/api/v1/organization/website', website);
      toast.success('Website global settings updated successfully');
    } catch (e) {
      toast.error('Failed to save website settings');
    } finally {
      setSaving(false);
    }
  };

  const updateBranchOverride = async (hotelId: number, data: any) => {
    try {
      const { data: updated } = await api.put(`/api/v1/organization/website/overrides/${hotelId}`, data);
      toast.success('Branch override saved');
      // Update local state
      setBranches(branches.map(b => b.id === hotelId ? { ...b, website_override: updated.data } : b));
    } catch (e) {
      toast.error('Failed to update branch override');
    }
  };

  const handleAiEnhance = async (field: string) => {
    try {
      setActiveAiField(field);
      setSaving(true);
      // Mock AI call delay
      await new Promise(r => setTimeout(r, 1500));
      
      const suggestions: any = {
        description: "Experience the pinnacle of hospitality where tradition meets modern luxury. Discover a world of comfort across our exclusive collection of premier hotel destinations.",
        about_text: "Founded on the principles of excellence and refined elegance, our hotel group has been redefining hospitality for over two decades. Each of our locations offers a unique blend of local character and global standards."
      };

      setWebsite((prev: any) => ({ ...prev, [field]: suggestions[field] || prev[field] }));
      toast.success('AI generation complete!');
    } catch (e) {
      toast.error('AI failed to generate text');
    } finally {
      setActiveAiField(null);
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-8 pb-24">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent">
            Website Creator
          </h1>
          <p className="text-muted-foreground mt-1">Manage your global hotel group portal and unique branch websites.</p>
        </div>
        <div className="flex items-center gap-3">
          <Button variant="outline" className="gap-2" onClick={() => window.open(`http://localhost:3001/group/${website?.slug}`, '_blank')}>
            <ExternalLink size={16} />
            Preview Site
          </Button>
          <Button className="gap-2 shadow-lg shadow-primary/20" onClick={handleSaveGroup} disabled={saving}>
            {saving ? <Plus className="animate-spin h-4 w-4" /> : <Save size={16} />}
            Save All Changes
          </Button>
        </div>
      </div>

      <Tabs defaultValue="global" className="space-y-6">
        <TabsList className="bg-muted/50 p-1 border border-border/50">
          <TabsTrigger value="global" className="gap-2">
            <Globe size={16} /> Global Branding
          </TabsTrigger>
          <TabsTrigger value="branches" className="gap-2">
            <MapPin size={16} /> Branch Overrides
          </TabsTrigger>
          <TabsTrigger value="design" className="gap-2">
            <Palette size={16} /> Design System
          </TabsTrigger>
        </TabsList>

        <TabsContent value="global" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card className="border-border/40 shadow-sm">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle>Main Identity</CardTitle>
                  <Sparkles size={18} className="text-primary" />
                </div>
                <CardDescription>Define how your hotel group appears on the global landing page.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>Group Title</Label>
                  <Input 
                    placeholder="e.g. Royal Springs Luxury Group" 
                    value={website?.title || ''} 
                    onChange={e => setWebsite({...website, title: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label>URL Slug</Label>
                  <div className="flex gap-2">
                    <div className="bg-muted px-3 flex items-center rounded-md border border-border/50 text-xs text-muted-foreground">
                      /group/
                    </div>
                    <Input 
                      placeholder="royal-springs" 
                      value={website?.slug || ''} 
                      onChange={e => setWebsite({...website, slug: e.target.value})}
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Contact Email</Label>
                    <Input 
                      placeholder="reservations@hotelgroup.com" 
                      value={website?.email || ''} 
                      onChange={e => setWebsite({...website, email: e.target.value})}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Contact Phone</Label>
                    <Input 
                      placeholder="+234 ..." 
                      value={website?.phone || ''} 
                      onChange={e => setWebsite({...website, phone: e.target.value})}
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Group Address</Label>
                  <Input 
                    placeholder="Headquarters address..." 
                    value={website?.address || ''} 
                    onChange={e => setWebsite({...website, address: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label>Hero Description</Label>
                    <Button 
                      variant="ghost" 
                      size="sm" 
                      className="h-6 gap-1 text-[10px] text-primary hover:text-primary/80"
                      onClick={() => handleAiEnhance('description')}
                      disabled={saving}
                    >
                      <Sparkles size={10} /> {saving && activeAiField === 'description' ? 'Thinking...' : 'AI Enhance'}
                    </Button>
                  </div>
                  <Textarea 
                    placeholder="Describe your hotel group in 1-2 powerful sentences..." 
                    className="min-h-[100px] resize-none"
                    value={website?.description || ''}
                    onChange={e => setWebsite({...website, description: e.target.value})}
                  />
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/40 shadow-sm">
              <CardHeader>
                <CardTitle>Global Assets</CardTitle>
                <CardDescription>Primary logo and banner that appears across all basic templates.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <ImageUpload 
                  label="Group Logo"
                  value={website?.logo_url || ''}
                  onChange={url => setWebsite({...website, logo_url: url})}
                  folder="logos"
                />
                <ImageUpload 
                  label="Main Hero Banner"
                  value={website?.banner_url || ''}
                  onChange={url => setWebsite({...website, banner_url: url})}
                  folder="banners"
                />
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="branches" className="space-y-6">
          <div className="grid gap-4">
            {branches.map((branch) => (
              <Card key={branch.id} className="border-border/40 overflow-hidden group hover:border-primary/30 transition-all">
                <div className="flex flex-col md:flex-row md:items-stretch">
                  <div className="w-full md:w-64 h-40 md:h-auto bg-muted/50 border-r border-border/40 overflow-hidden relative">
                    {branch.website_override?.primary_image_url ? (
                      <img src={branch.website_override.primary_image_url} className="h-full w-full object-cover" />
                    ) : (
                      <div className="h-full w-full flex items-center justify-center">
                        <ImageIcon size={24} className="text-muted-foreground/20" />
                      </div>
                    )}
                  </div>
                  <div className="flex-1 p-6 space-y-4">
                    <div className="flex items-start justify-between">
                      <div>
                        <h3 className="text-lg font-bold">{branch.name}</h3>
                        <p className="text-sm text-muted-foreground">{branch.address}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Label htmlFor={`use-group-${branch.id}`} className="text-xs">Use Group Branding</Label>
                        <Switch 
                          id={`use-group-${branch.id}`} 
                          checked={branch.website_override?.use_group_branding !== false}
                          onCheckedChange={(checked) => {
                            updateBranchOverride(branch.id, { 
                              ...branch.website_override, 
                              use_group_branding: checked 
                            });
                          }}
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                      <div className="space-y-2">
                        <Label className="text-xs font-semibold">Local Identity Overrides</Label>
                        <Input 
                          placeholder="Unique branch tagline..." 
                          className="h-9 text-sm"
                          value={branch.website_override?.custom_title || ''}
                          onChange={(e) => {
                            const updatedBranches = branches.map(b => 
                              b.id === branch.id 
                                ? { ...b, website_override: { ...b.website_override, custom_title: e.target.value } }
                                : b
                            );
                            setBranches(updatedBranches);
                          }}
                          onBlur={() => updateBranchOverride(branch.id, branch.website_override)}
                        />
                      </div>
                      <div className="space-y-2">
                        <ImageUpload 
                          label="Branch Hero Image"
                          value={branch.website_override?.primary_image_url || ''}
                          onChange={url => {
                            updateBranchOverride(branch.id, { 
                              ...branch.website_override, 
                              primary_image_url: url 
                            });
                          }}
                          folder="branches"
                        />
                      </div>
                    </div>

                    {branch.website_override?.use_group_branding === false && (
                      <div className="pt-4 border-t border-border/40">
                        <Dialog>
                          <DialogTrigger
                            nativeButton={true}
                            render={
                              <Button variant="outline" size="sm" className="gap-2 border-primary/20 text-primary hover:bg-primary/5 font-semibold">
                                <Palette size={14} />
                                Full Branch Design Orchestrator
                              </Button>
                            }
                          />
                          <DialogContent className="max-w-[95vw] w-full max-h-[95vh] overflow-y-auto p-0 border-none shadow-2xl">
                            <div className="flex flex-col h-full bg-background">
                              <div className="p-6 border-b border-border/40 bg-muted/20">
                                <div className="flex items-center justify-between">
                                  <div>
                                    <DialogTitle className="text-2xl font-bold">Branch Design Orchestrator</DialogTitle>
                                    <p className="text-sm text-muted-foreground mt-1">Sculpt the unique identity for <span className="text-primary font-bold">{branch.name}</span></p>
                                  </div>
                                </div>
                              </div>
                              <div className="p-8">
                                 <DesignSystemEditor 
                                  title="Autonomous Branch Aesthetics"
                                  description="Refine this specific location's visual tokens. These overrides take precedence over global group settings."
                                  settings={branch.website_override?.design_settings || website?.design_settings}
                                  branding={{
                                    title: branch.website_override?.custom_title || branch.name,
                                    description: branch.website_override?.custom_description || website?.description || '',
                                    logo_url: website?.logo_url,
                                    banner_url: branch.website_override?.primary_image_url || website?.banner_url,
                                    email: website?.email,
                                    phone: website?.phone,
                                    address: branch.address
                                  }}
                                  onChange={(settings) => {
                                    updateBranchOverride(branch.id, { 
                                      ...branch.website_override, 
                                      design_settings: settings 
                                    });
                                  }}
                                />
                              </div>
                            </div>
                          </DialogContent>
                        </Dialog>
                      </div>
                    )}
                  </div>
                  <div className="p-6 border-l border-border/40 flex items-center justify-center">
                    <Button variant="outline" size="sm" className="gap-2 group/btn">
                      Edit Full Content
                      <ChevronRight size={14} className="group-hover/btn:translate-x-1 transition-transform" />
                    </Button>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="design" className="space-y-6">
          <DesignSystemEditor 
            settings={website?.design_settings}
            branding={{
              title: website?.title || '',
              description: website?.description || '',
              logo_url: website?.logo_url,
              banner_url: website?.banner_url,
              email: website?.email,
              phone: website?.phone,
              address: website?.address
            }}
            branches={branches}
            onChange={(settings) => setWebsite({ ...website, design_settings: settings })}
          />
        </TabsContent>
      </Tabs>
    </div>
  );
}
