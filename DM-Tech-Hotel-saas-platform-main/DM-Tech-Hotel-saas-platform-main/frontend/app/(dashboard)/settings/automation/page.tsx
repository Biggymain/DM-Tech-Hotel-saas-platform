'use client';

import * as React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { ZapIcon, PlusIcon, ArrowRightIcon } from 'lucide-react';

export default function AutomationWorkflowPage() {
  const [rules, setRules] = React.useState([
    { id: 1, trigger: 'reservation_checked_out', action: 'create_housekeeping_task', is_active: true, run_count: 145 },
    { id: 2, trigger: 'room_marked_dirty', action: 'assign_housekeeping_staff', is_active: true, run_count: 890 },
    { id: 3, trigger: 'maintenance_request_created', action: 'notify_technician', is_active: false, run_count: 12 },
  ]);

  const toggleRule = (id: number) => {
    setRules(rules.map(rule => rule.id === id ? { ...rule, is_active: !rule.is_active } : rule));
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Automation Engine</h2>
          <p className="text-muted-foreground">Define Trigger → Action event listeners to automate operational overhead.</p>
        </div>
        <div className="flex gap-2">
          <Button>
            <PlusIcon className="mr-2 h-4 w-4" />
            Create Rule
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Active Event Listeners</CardTitle>
          <CardDescription>Rules executed directly within the backend Event loop across all hotel tenants.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Trigger Event</TableHead>
                  <TableHead></TableHead>
                  <TableHead>System Action</TableHead>
                  <TableHead>Runs</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Manage</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rules.map((rule) => (
                  <TableRow key={rule.id}>
                    <TableCell className="font-mono text-sm bg-muted/30">{rule.trigger}</TableCell>
                    <TableCell><ArrowRightIcon className="h-4 w-4 text-muted-foreground" /></TableCell>
                    <TableCell className="font-mono text-sm font-semibold">{rule.action}</TableCell>
                    <TableCell>{rule.run_count}</TableCell>
                    <TableCell>
                      <Switch 
                        checked={rule.is_active} 
                        onCheckedChange={() => toggleRule(rule.id)}
                      />
                    </TableCell>
                    <TableCell className="text-right space-x-2">
                      <Button variant="ghost" size="sm">Edit</Button>
                      <Button variant="ghost" size="sm" className="text-muted-foreground">Logs</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
