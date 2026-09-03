'use client';

import * as React from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { 
  UtensilsCrossed, 
  PlusIcon, 
  Search, 
  Edit2, 
  Trash2, 
  Loader2, 
  Coffee, 
  Wine, 
  ChefHat,
  Eye,
  Settings2,
  ChevronRight,
  Share2,
  Users
} from 'lucide-react';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import api from '@/lib/api';
import { toast } from 'sonner';
import { useParams, useSearchParams } from 'next/navigation';
import { useAuth } from '@/context/AuthProvider';

function POSContent() {
  const params = useParams();
  const branchSlug = params.slug;
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = React.useState('');
  const searchParams = useSearchParams();
  const outletIdParam = searchParams.get('outlet_id');
  const [activeTab, setActiveTab] = React.useState(outletIdParam || user?.outlet_id?.toString() || "all");

  React.useEffect(() => {
    if (outletIdParam) {
      setActiveTab(outletIdParam);
    }
  }, [outletIdParam]);

  const { data: outlets = [], isLoading: loadingOutlets } = useQuery({
    queryKey: ['branch-outlets', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/outlets');
      return data || [];
    }
  });

  const { data: menuItems = [], isLoading: loadingItems } = useQuery({
    queryKey: ['branch-menu-items', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/menu/items');
      return data || [];
    }
  });

  const { data: staffData = { data: [] }, isLoading: loadingStaff } = useQuery({
    queryKey: ['branch-staff', branchSlug],
    queryFn: () => api.get(`/api/v1/users`).then(res => res.data),
  });

  const staff = staffData.data;

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/menu/items/${id}`),
    onSuccess: () => {
      toast.success('Item removed from catalog');
      queryClient.invalidateQueries({ queryKey: ['branch-menu-items'] });
    }
  });

  const [isNewItemOpen, setIsNewItemOpen] = React.useState(false);
  const [itemForm, setItemForm] = React.useState({
    name: '',
    menu_category_id: '',
    price: 0,
    description: '',
    outlet_id: activeTab === 'all' ? 'none' : activeTab,
    image_url: ''
  });

  // Update itemForm when modal opens or activeTab changes
  React.useEffect(() => {
    if (isNewItemOpen) {
      setItemForm(prev => ({
        ...prev,
        outlet_id: activeTab === 'all' ? 'none' : activeTab
      }));
    }
  }, [isNewItemOpen, activeTab]);

  const { data: categories = [] } = useQuery({
    queryKey: ['menu-categories'],
    queryFn: () => api.get('/api/v1/menu/categories').then(res => res.data),
  });

  const addItemMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/menu/items', data),
    onSuccess: () => {
      toast.success('Item added to menu!');
      queryClient.invalidateQueries({ queryKey: ['branch-menu-items'] });
      setIsNewItemOpen(false);
      setItemForm({ name: '', menu_category_id: '', price: 0, description: '', outlet_id: 'none', image_url: '' });
    }
  });

  const [isNewCategoryOpen, setIsNewCategoryOpen] = React.useState(false);
  const [categoryForm, setCategoryForm] = React.useState({
    name: '',
    station: '',
    description: '',
    outlet_id: activeTab === 'all' ? 'none' : activeTab
  });

  // Update categoryForm when modal opens or activeTab changes
  React.useEffect(() => {
    if (isNewCategoryOpen) {
      setCategoryForm(prev => ({
        ...prev,
        outlet_id: activeTab === 'all' ? 'none' : activeTab
      }));
    }
  }, [isNewCategoryOpen, activeTab]);

  const addCategoryMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/menu/categories', data),
    onSuccess: () => {
      toast.success('Category created!');
      queryClient.invalidateQueries({ queryKey: ['menu-categories'] });
      setIsNewCategoryOpen(false);
      setCategoryForm({ name: '', station: '', description: '', outlet_id: 'none' });
    }
  });

  const [deployItem, setDeployItem] = React.useState<any>(null);
  const [deployForm, setDeployForm] = React.useState({ outlet_id: '', price: '' });

  const updateOutletMutation = useMutation({
    mutationFn: (data: { id: number, tables_count: number }) => api.put(`/api/v1/outlets/${data.id}`, { tables_count: data.tables_count }),
    onSuccess: () => {
      toast.success('Seating capacity updated');
      queryClient.invalidateQueries({ queryKey: ['branch-outlets'] });
    }
  });

  const deployMutation = useMutation({
    mutationFn: (data: any) => api.post(`/api/v1/menu/items/${deployItem.id}/duplicate`, data),
    onSuccess: () => {
      toast.success('Item deployed to outlet');
      setDeployItem(null);
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Deployment failed');
    }
  });

  const filteredItems = (items: any[]) => items.filter((item: any) => 
    item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    item.menuCategory?.name?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const filteredStaff = (staffMembers: any[]) => staffMembers.filter((m: any) =>
    m.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    m.roles.some((r: any) => r.name.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  const renderMenuTable = (items: any[], title: string) => (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>{title}</CardTitle>
            <CardDescription>Menu items currently active.</CardDescription>
          </div>
          <div className="flex items-center gap-2">
             <Badge variant="outline" className="bg-primary/5">{filteredItems(items).length} Items</Badge>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div className="rounded-xl border border-border/50 overflow-hidden">
          <Table>
            <TableHeader className="bg-muted/30">
              <TableRow>
                <TableHead className="w-[300px]">Item Details</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Availability</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loadingItems ? [...Array(5)].map((_, i) => (
                <TableRow key={i}><TableCell colSpan={5} className="h-16 animate-pulse bg-muted/20" /></TableRow>
              )) : filteredItems(items).map((item: any) => (
                <TableRow key={item.id} className="hover:bg-muted/10 transition-colors">
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-lg bg-muted flex items-center justify-center overflow-hidden border">
                        {item.image_url ? <img src={item.image_url} alt="" className="h-full w-full object-cover" /> : <UtensilsCrossed className="h-5 w-5 text-muted-foreground/50" />}
                      </div>
                      <div>
                        <div className="font-semibold text-sm">{item.name}</div>
                        <div className="text-[10px] text-muted-foreground line-clamp-1">{item.description || 'No description provided.'}</div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary" className="text-[10px] uppercase tracking-wider font-bold">
                      {item.menuCategory?.name || 'Uncategorized'}
                      {item.menuCategory?.station && <span className="ml-1 opacity-50">({item.menuCategory.station})</span>}
                    </Badge>
                  </TableCell>
                  <TableCell className="font-bold text-sm">
                    ₦{Number(item.price).toLocaleString()}
                  </TableCell>
                  <TableCell>
                    <Badge variant={item.is_available ? 'default' : 'destructive'} className={item.is_available ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : ''}>
                      {item.is_available ? 'Available' : 'Sold Out'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right space-x-1">
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-primary">
                      <Edit2 className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-muted-foreground hover:text-primary"
                      onClick={() => {
                          setDeployItem(item);
                          setDeployForm({ outlet_id: '', price: item.price.toString() });
                      }}
                    >
                      <Share2 className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-muted-foreground hover:text-destructive"
                      onClick={() => deleteMutation.mutate(item.id)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
              {filteredItems(items).length === 0 && !loadingItems && (
                <TableRow>
                  <TableCell colSpan={5} className="h-32 text-center text-muted-foreground">
                    No items found matching your search.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
    );

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">POS & Menu Management</h2>
          <p className="text-muted-foreground">Manage your property's drinks, kitchen menus, and pricing.</p>
        </div>
        <div className="flex gap-2">
          <Dialog open={isNewCategoryOpen} onOpenChange={setIsNewCategoryOpen}>
            <DialogTrigger render={
              <Button variant="outline" className="gap-2">
                <PlusIcon className="h-4 w-4" />
                New Category
              </Button>
            } />
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create Menu Category</DialogTitle>
                <CardDescription>Group your items and assign them to a preparation station.</CardDescription>
              </DialogHeader>
              <div className="grid gap-4 py-4">
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Name</Label>
                  <Input 
                    className="col-span-3"
                    value={categoryForm.name}
                    onChange={e => setCategoryForm({...categoryForm, name: e.target.value})}
                    placeholder="e.g. Appetizers, Cocktails"
                  />
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Station</Label>
                   <Select onValueChange={(val: string | null) => setCategoryForm({...categoryForm, station: val || ''})} value={String(categoryForm.station ?? "none")}>
                    <SelectTrigger className="col-span-3">
                      <SelectValue placeholder="Select station (e.g. Grill)" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Grill Kitchen">Grill Kitchen</SelectItem>
                      <SelectItem value="Main Kitchen">Main Kitchen</SelectItem>
                      <SelectItem value="Bar">Bar</SelectItem>
                      <SelectItem value="Pastry">Pastry</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Outlet</Label>
                   <Select onValueChange={(val: string | null) => setCategoryForm({...categoryForm, outlet_id: val || ''})} value={String(categoryForm.outlet_id ?? "none")}>
                    <SelectTrigger className="col-span-3">
                      <SelectValue placeholder="General / All" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">General / All</SelectItem>
                      {outlets.map((outlet: any) => (
                        <SelectItem key={outlet.id} value={outlet.id?.toString() || "none"}>{outlet.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <DialogFooter>
                <Button 
                  onClick={() => addCategoryMutation.mutate({
                    ...categoryForm, 
                    outlet_id: categoryForm.outlet_id === 'none' ? null : categoryForm.outlet_id
                  })}
                  disabled={!categoryForm.name || addCategoryMutation.isPending}
                >
                  {addCategoryMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  Create Category
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isNewItemOpen} onOpenChange={setIsNewItemOpen}>
          <DialogTrigger render={
            <Button className="gap-2 shadow-lg shadow-primary/20">
              <PlusIcon className="h-4 w-4" />
              Add Menu Item
            </Button>
          } />
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Add New Menu Item</DialogTitle>
              <CardDescription>Add a new dish or drink to your menu catalog.</CardDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Name</Label>
                <Input 
                  className="col-span-3"
                  value={itemForm.name}
                  onChange={e => setItemForm({...itemForm, name: e.target.value})}
                  placeholder="Heineken, Pizza, etc."
                />
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Price</Label>
                <Input 
                  type="number"
                  className="col-span-3"
                  value={itemForm.price}
                  onChange={e => setItemForm({...itemForm, price: parseFloat(e.target.value)})}
                />
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Category (Optional)</Label>
                <div className="col-span-3">
                   <Select onValueChange={(val: string | null) => setItemForm({...itemForm, menu_category_id: val || ''})} value={itemForm.menu_category_id || "none"}>
                     <SelectTrigger>
                       <SelectValue placeholder="Select category (Optional)" />
                     </SelectTrigger>
                     <SelectContent>
                       <SelectItem value="none">None / Generic</SelectItem>
                       {categories.map((cat: any) => (
                         <SelectItem key={cat.id} value={cat.id?.toString() || ""}>
                           {cat.name}
                         </SelectItem>
                       ))}
                     </SelectContent>
                   </Select>
                </div>
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Assign to Outlet</Label>
                <Select onValueChange={(val: string | null) => setItemForm({...itemForm, outlet_id: val || ''})} value={itemForm.outlet_id || "none"}>
                  <SelectTrigger className="col-span-3">
                    <SelectValue placeholder="General / All" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">General / All (Main Store)</SelectItem>
                      {outlets.map((outlet: any) => (
                        <SelectItem key={outlet.id} value={outlet.id?.toString() || "none"}>{outlet.name}</SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Description</Label>
                <Input 
                  className="col-span-3"
                  value={itemForm.description}
                  onChange={e => setItemForm({...itemForm, description: e.target.value})}
                  placeholder="Optional description"
                />
              </div>
            </div>
            <DialogFooter>
              <Button 
                className="w-full sm:w-auto"
                onClick={() => addItemMutation.mutate({
                  ...itemForm, 
                  menu_category_id: itemForm.menu_category_id === 'none' ? null : itemForm.menu_category_id,
                  outlet_id: itemForm.outlet_id === 'none' ? null : itemForm.outlet_id
                })}
                disabled={!itemForm.name || isNaN(itemForm.price) || itemForm.price < 0 || addItemMutation.isPending}
              >
                {addItemMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Add to Catalog
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
          <TabsList className="bg-muted/50 p-1 rounded-xl h-auto flex-wrap">
            {(!user?.outlet_id || user?.is_super_admin) && (
              <TabsTrigger value="all" className="rounded-lg px-4 py-2 data-[state=active]:bg-background data-[state=active]:shadow-sm">
                All Items
              </TabsTrigger>
            )}
            {outlets.filter((o: any) => !user?.outlet_id || o.id === user.outlet_id).map((outlet: any) => (
              <TabsTrigger key={outlet.id} value={outlet.id.toString()} className="rounded-lg px-4 py-2 data-[state=active]:bg-background data-[state=active]:shadow-sm">
                <div className="flex items-center gap-2">
                  {outlet.type === 'bar' ? <Wine className="h-3.5 w-3.5" /> : <ChefHat className="h-3.5 w-3.5" />}
                  {outlet.name}
                </div>
              </TabsTrigger>
            ))}
          </TabsList>

          <div className="flex items-center gap-3 bg-card border rounded-xl px-3 py-1.5 shadow-sm min-w-[300px]">
            <Search className="h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search dishes, drinks, or staff..."
              className="border-none shadow-none focus-visible:ring-0 bg-transparent h-8"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        <TabsContent value="all" className="space-y-6">
           {renderMenuTable(menuItems, "Central Menu Catalog")}
        </TabsContent>

        {outlets.map((outlet: any) => (
          <TabsContent key={outlet.id} value={outlet.id.toString()} className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div className="lg:col-span-2">
                {renderMenuTable(menuItems.filter((i: any) => i.outlet_id === outlet.id), `${outlet.name} Menu`)}
              </div>
              <div className="lg:col-span-1 space-y-6">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                      <Users className="h-4 w-4 text-primary" />
                      Assigned Staff
                    </CardTitle>
                    <CardDescription>Members currently assigned to this outlet.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {loadingStaff ? <div className="space-y-2"><div className="h-10 bg-muted animate-pulse rounded" /></div> :
                     filteredStaff(staff.filter((s: any) => s.outlet_id === outlet.id)).map((member: any) => (
                      <div key={member.id} className="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors">
                        <div className="flex items-center gap-3">
                          <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-xs">
                            {member.name.charAt(0)}
                          </div>
                          <div>
                            <div className="text-sm font-semibold">{member.name}</div>
                            <div className="text-[10px] text-muted-foreground">{member.roles[0]?.name}</div>
                          </div>
                        </div>
                        <Badge variant={member.is_on_duty ? "default" : "secondary"} className="text-[8px] h-4">
                          {member.is_on_duty ? "On Duty" : "Off"}
                        </Badge>
                      </div>
                    ))}
                    {staff.filter((s: any) => s.outlet_id === outlet.id).length === 0 && (
                       <div className="text-center py-8 text-muted-foreground text-xs italic border-2 border-dashed rounded-xl">
                          No staff assigned to this outlet.
                       </div>
                    )}
                    <Button variant="outline" size="sm" className="w-full text-xs h-8" asChild>
                       <Link href={`/branch/${branchSlug}/staff?outlet_id=${outlet.id}`}>Manage Outlet Staff</Link>
                    </Button>
                  </CardContent>
                </Card>

                <Card className="bg-gradient-to-br from-primary/5 to-primary/10 border-primary/20">
                  <CardContent className="p-4 space-y-4">
                    <div className="flex items-center justify-between text-primary font-semibold text-sm">
                      <div className="flex items-center gap-2">
                        <Settings2 className="h-4 w-4" />
                        Outlet Context
                      </div>
                      <Badge variant="outline" className="text-[10px] bg-primary/20 border-primary/30 text-primary">
                        {outlet.tables_count || 0} Tables
                      </Badge>
                    </div>
                    <p className="text-[11px] leading-relaxed text-muted-foreground">
                      Staff assigned to this outlet can only process orders and see reports for <strong>{outlet.name}</strong>.
                    </p>
                    
                    <div className="pt-2 border-t border-primary/10 space-y-2">
                        <Label className="text-[10px] uppercase font-bold text-muted-foreground">Manage Seating</Label>
                        <div className="flex gap-2">
                            <Input 
                                type="number" 
                                className="h-8 text-xs bg-background/50" 
                                placeholder="Number of tables"
                                defaultValue={outlet.tables_count}
                                onBlur={(e) => {
                                    const val = parseInt(e.target.value);
                                    if (!isNaN(val) && val !== outlet.tables_count) {
                                        updateOutletMutation.mutate({ id: outlet.id, tables_count: val });
                                    }
                                }}
                            />
                            <Button size="sm" variant="outline" className="h-8 text-[10px] px-2 shadow-inner" asChild>
                                <Link href={`/branch/${branchSlug}/guest-portal?outlet_id=${outlet.id}&generate_qr=true`}>
                                    QR Codes
                                </Link>
                            </Button>
                        </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          </TabsContent>
        ))}
      </Tabs>
      <Dialog open={!!deployItem} onOpenChange={(open) => !open && setDeployItem(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Deploy to Outlet</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
             <div className="space-y-1.5">
               <Label>Target Outlet</Label>
               <Select 
                 onValueChange={(val: any) => setDeployForm({...deployForm, outlet_id: val})}
                 value={deployForm.outlet_id}
               >
                 <SelectTrigger>
                   <SelectValue placeholder="Select outlet" />
                 </SelectTrigger>
                 <SelectContent>
                   {outlets.filter((o: any) => o.id !== deployItem?.outlet_id).map((o: any) => (
                     <SelectItem key={o.id} value={o.id.toString()}>{o.name}</SelectItem>
                   ))}
                 </SelectContent>
               </Select>
             </div>
             <div className="space-y-1.5">
               <Label>Outlet Price</Label>
               <Input 
                 type="number"
                 value={deployForm.price}
                 onChange={(e) => setDeployForm({...deployForm, price: e.target.value})}
               />
               <p className="text-[10px] text-muted-foreground italic">
                 Set a custom price for this specific outlet (e.g. higher price for Pool Bar).
               </p>
             </div>
          </div>
          <DialogFooter>
             <Button variant="outline" onClick={() => setDeployItem(null)}>Cancel</Button>
             <Button 
               disabled={!deployForm.outlet_id || !deployForm.price || deployMutation.isPending}
               onClick={() => deployMutation.mutate(deployForm)}
             >
               {deployMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
               Deploy Item
             </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

export default function BranchPOSPage() {
  return (
    <React.Suspense fallback={<div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>}>
      <POSContent />
    </React.Suspense>
  );
}
