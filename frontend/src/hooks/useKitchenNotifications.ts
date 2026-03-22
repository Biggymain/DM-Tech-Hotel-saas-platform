import { useEffect } from 'react';
import { initEcho } from '@/src/lib/echo';
import { toast } from 'sonner'; // Assuming sonner is used for toasts

export const useKitchenNotifications = (user: any) => {
    useEffect(() => {
        if (!user?.id || !user?.token) return;

        const echo = initEcho(user.token);
        const hotelId = user.hotel_id;
        const branchId = user.branch_id ?? hotelId;
        const stationId = user.kitchen_station_id;

        // 1. Listen for new tickets (KDS)
        if (user.role === 'kitchen-staff' || user.role === 'kitchen-manager') {
            echo.private(`hotel.${hotelId}.branch.${branchId}.station.${stationId}`)
                .listen('.kitchen.ticket.updated', (data: any) => {
                    if (data.status === 'queued') {
                        toast.info(`New Order: #${data.ticket_number} for Table ${data.table_number}`);
                        // Play sound or refresh list
                    }
                });
        }

        // 2. Listen for "Ready" alerts (Waitress)
        echo.private(`hotel.${hotelId}.waiter.${user.id}`)
            .listen('.kitchen.ticket.updated', (data: any) => {
                if (data.status === 'ready') {
                    toast.success(`Order Ready! #${data.order_number} for Table ${data.table_number}`);
                    // Trigger haptic feedback or sound
                }
            });

        return () => {
            echo.disconnect();
        };
    }, [user]);
};
