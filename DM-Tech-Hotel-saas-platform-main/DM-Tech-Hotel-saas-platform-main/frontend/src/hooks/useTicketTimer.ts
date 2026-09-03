import { useState, useEffect } from 'react';
import { differenceInMinutes, parseISO } from 'date-fns';

export const useTicketTimer = (firedAt: string, onThresholdExceeded?: () => void) => {
    const [elapsed, setElapsed] = useState(0);
    const [statusColor, setStatusColor] = useState('text-emerald-500');

    useEffect(() => {
        const calculate = () => {
            const minutes = differenceInMinutes(new Date(), parseISO(firedAt));
            setElapsed(minutes);

            if (minutes >= 20) {
                setStatusColor('text-rose-600');
                if (onThresholdExceeded) onThresholdExceeded();
            } else if (minutes >= 15) {
                setStatusColor('text-amber-500');
            } else {
                setStatusColor('text-emerald-500');
            }
        };

        calculate();
        const interval = setInterval(calculate, 60000); // Update every minute

        return () => clearInterval(interval);
    }, [firedAt]);

    return { elapsed, statusColor };
};
