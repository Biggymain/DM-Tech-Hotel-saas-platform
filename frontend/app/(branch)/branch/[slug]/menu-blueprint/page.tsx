'use client';

import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
  Wine, 
  ChefHat,
  Share2
} from 'lucide-react';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import api from '@/lib/api';
import { toast } from 'sonner';
import { useParams } from 'next/navigation';
import { useAuth } from '@/context/AuthProvider';

export default function MenuBlueprintPage() {
  const params = useParams();
  const branchSlug = params.slug;
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = React.useState('');
  const [activeTab, setActiveTab] = React.useState("all");

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
    outlet_id: 'none',
    image_url: ''
  });

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
    outlet_id: 'none'
  });

  const addCategoryMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/menu/categories', data),
    onSuccess: () => {
      toast.success('Category created!');
      queryClient.invalidateQueries({ queryKey: ['menu-categories'] });
      setIsNewCategoryOpen(false);
      setCategoryForm({ name: '', station: '', description: '', outlet_id: 'none' });
    }
  });

  const filteredItems = (items: any[]) => items.filter((item: any) => 
    item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    item.menuCategory?.name?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const renderMenuTable = (items: any[], title: string) => (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>{title}</CardTitle>
            <CardDescription>Configure static menu properties here.</CardDescription>
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
                      className="h-8 w-8 text-muted-foreground hover:text-destructive"
                      onClick={() => deleteMutation.mutate(item.id)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
    );

  return (
    <div className="space-y-6">
      <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-6">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Menu Blueprint</h2>
          <p className="text-muted-foreground">Static menu configuration, pricing, and category mapping.</p>
        </div>
        <div className="flex gap-2">
           <Button variant="outline" className="gap-2" onClick={() => setIsNewCategoryOpen(true)}>
              <PlusIcon className="h-4 w-4" />
              New Category
           </Button>
           <Button className="gap-2" onClick={() => setIsNewItemOpen(true)}>
              <PlusIcon className="h-4 w-4" />
              Add Menu Item
           </Button>
        </div>
      </header>

      <div className="flex items-center gap-3 bg-card border rounded-xl px-3 py-1.5 shadow-sm max-w-md">
        <Search className="h-4 w-4 text-muted-foreground" />
        <Input
          placeholder="Filter catalog..."
          className="border-none shadow-none focus-visible:ring-0 bg-transparent h-8"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
        />
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
         <TabsList className="bg-muted/50 p-1 rounded-xl h-auto mb-6">
            <TabsTrigger value="all" className="rounded-lg px-4 py-2">All Items</TabsTrigger>
            {outlets.map((outlet: any) => (
              <TabsTrigger key={outlet.id} value={outlet.id.toString()} className="rounded-lg px-4 py-2">
                {outlet.name}
              </TabsTrigger>
            ))}
         </TabsList>

         <TabsContent value="all">
            {renderMenuTable(menuItems, "Central Menu Catalog")}
         </TabsContent>
         {outlets.map((outlet: any) => (
           <TabsContent key={outlet.id} value={outlet.id.toString()}>
              {renderMenuTable(menuItems.filter((i: any) => i.outlet_id === outlet.id), `${outlet.name} Configuration`)}
           </TabsContent>
         ))}
      </Tabs>

      {/* MODALS (Simplified for migration) */}
      <Dialog open={isNewCategoryOpen} onOpenChange={setIsNewCategoryOpen}>
        <DialogContent>
            <DialogHeader><DialogTitle>Create Menu Category</DialogTitle></DialogHeader>
            <div className="grid gap-4 py-4">
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Name</Label>
                  <Input className="col-span-3" value={categoryForm.name} onChange={e => setCategoryForm({...categoryForm, name: e.target.value})} />
                </div>
            </div>
            <DialogFooter>
                <Button onClick={() => addCategoryMutation.mutate(categoryForm)}>Create</Button>
            </DialogFooter>
        </DialogContent>
      </Dialog>
      
      <Dialog open={isNewItemOpen} onOpenChange={setIsNewItemOpen}>
        <DialogContent>
            <DialogHeader><DialogTitle>Add Menu Item</DialogTitle></DialogHeader>
            <div className="grid gap-4 py-4">
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Name</Label>
                  <Input className="col-span-3" value={itemForm.name} onChange={e => setItemForm({...itemForm, name: e.target.value})} />
                </div>
                <div className="grid grid-cols-4 items-center gap-4">
                  <Label className="text-right">Price</Label>
                  <Input type="number" className="col-span-3" value={itemForm.price} onChange={e => setItemForm({...itemForm, price: parseFloat(e.target.value)})} />
                </div>
            </div>
            <DialogFooter>
                <Button onClick={() => addItemMutation.mutate(itemForm)}>Save Item</Button>
            </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
