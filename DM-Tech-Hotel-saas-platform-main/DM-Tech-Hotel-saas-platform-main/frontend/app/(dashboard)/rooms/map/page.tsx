'use client';

import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { 
    LayoutGrid, List, Info, Timer, ShieldAlert, 
    CheckCircle2, Eraser, Map as MapIcon, ChevronRight
} from 'lucide-react';
import Link from 'next/link';

interface Room {
    id: number;
    room_number: string;
    floor: string;
    status: 'available' | 'occupied' | 'maintenance' | 'out_of_order';
    housekeeping_status: 'clean' | 'dirty' | 'cleaning' | 'inspecting';
    room_type: {
        name: string;
    };
}

const StatusBadge = ({ room }: { room: Room }) => {
    let color = 'bg-slate-400';
    let icon = <Info size={12} />;
    let label: string = room.status;

    if (room.status === 'occupied') {
        color = 'bg-rose-500';
        icon = <ShieldAlert size={12} />;
        label = 'Occupied';
    } else if (room.housekeeping_status === 'dirty' || room.housekeeping_status === 'cleaning') {
        color = 'bg-amber-500';
        icon = <Eraser size={12} />;
        label = 'Cleaning';
    } else if (room.status === 'available' && room.housekeeping_status === 'clean') {
        color = 'bg-emerald-500';
        icon = <CheckCircle2 size={12} />;
        label = 'Available';
    } else if (room.status === 'maintenance' || room.status === 'out_of_order') {
        color = 'bg-slate-600';
        icon = <Timer size={12} />;
        label = 'Service';
    }

    return (
        <div className={`flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold text-white uppercase tracking-wider ${color} shadow-sm`}>
            {icon}
            {label}
        </div>
    );
};

export default function VisualRoomMap() {
    const [loading, setLoading] = useState(true);
    const [floors, setFloors] = useState<Record<string, Room[]>>({});

    useEffect(() => {
        fetchMap();
    }, []);

    const fetchMap = async () => {
        try {
            setLoading(true);
            const response = await axios.get(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/pms/rooms/map`);
            setFloors(response.data.data);
        } catch (error) {
            console.error('Failed to fetch room map', error);
            toast.error('Could not load room map');
        } finally {
            setLoading(false);
        }
    };

    if (loading) return (
        <div className="flex items-center justify-center min-h-[400px]">
            <div className="flex flex-col items-center gap-3">
                <div className="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p className="text-sm text-slate-500 font-medium animate-pulse">Scanning hotel floors...</p>
            </div>
        </div>
    );

    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
                        Visual Room Map
                        <span className="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded uppercase tracking-widest font-bold">LIVE OPS</span>
                    </h1>
                    <p className="text-slate-500 max-w-2xl mt-1">
                        Real-time floor-by-floor view of room status and housekeeping.
                    </p>
                </div>
                <div className="flex items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 rounded-xl shadow-sm">
                    <Link href="/rooms" className="flex items-center gap-2 px-4 py-2 text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors text-sm font-medium">
                        <List size={18} />
                        Table View
                    </Link>
                    <div className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold shadow-md">
                        <MapIcon size={18} />
                        Visual Map
                    </div>
                </div>
            </div>

            {/* Legend */}
            <div className="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-wrap items-center gap-6 justify-center md:justify-start">
                <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                    <div className="w-3 h-3 rounded-full bg-emerald-500"></div>
                    Available
                </div>
                <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                    <div className="w-3 h-3 rounded-full bg-rose-500"></div>
                    Occupied
                </div>
                <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                    <div className="w-3 h-3 rounded-full bg-amber-500"></div>
                    Cleaning Required
                </div>
                <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                    <div className="w-3 h-3 rounded-full bg-slate-600"></div>
                    Maintenance
                </div>
            </div>

            {/* Floors */}
            <div className="space-y-12">
                {Object.keys(floors).length > 0 ? Object.entries(floors).map(([floor, rooms]) => (
                    <div key={floor} className="space-y-6">
                        <div className="flex items-center gap-4">
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 whitespace-nowrap">
                                <ChevronRight className="text-indigo-500" />
                                {floor}
                            </h2>
                            <div className="h-[1px] w-full bg-slate-100 dark:bg-slate-800"></div>
                            <span className="text-xs font-medium text-slate-400 whitespace-nowrap uppercase tracking-widest">{rooms.length} Rooms</span>
                        </div>
                        
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                            {rooms.map((room) => (
                                <div 
                                    key={room.id}
                                    className="group relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 transition-all hover:shadow-xl hover:-translate-y-1 cursor-pointer overflow-hidden"
                                    onClick={() => toast.info(`Room ${room.room_number}: ${room.room_type.name}`)}
                                >
                                    {/* Status Indicator Stripe */}
                                    <div className={`absolute top-0 left-0 right-0 h-1.5 ${
                                        room.status === 'occupied' ? 'bg-rose-500' :
                                        (room.housekeeping_status === 'dirty' || room.housekeeping_status === 'cleaning' ? 'bg-amber-500' :
                                        (room.status === 'available' ? 'bg-emerald-500' : 'bg-slate-600'))
                                    }`} />

                                    <div className="flex flex-col items-center gap-2 text-center mt-2">
                                        <span className="text-2xl font-black text-slate-900 dark:text-white tracking-tighter group-hover:scale-110 transition-transform">
                                            {room.room_number}
                                        </span>
                                        <p className="text-[10px] text-slate-400 font-bold uppercase truncate w-full px-1">
                                            {room.room_type.name}
                                        </p>
                                        <StatusBadge room={room} />
                                    </div>

                                    {/* Tooltip-like overlay on hover */}
                                    <div className="absolute inset-0 bg-slate-900/80 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center p-2">
                                        <button className="text-[10px] text-white font-bold border border-white/20 rounded-lg px-2 py-1 hover:bg-white/10 transition-colors uppercase tracking-widest">
                                            Manage 
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )) : (
                    <div className="bg-white dark:bg-slate-900 p-20 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800 flex flex-col items-center text-center">
                        <div className="bg-slate-50 dark:bg-slate-800 p-4 rounded-full mb-4 text-slate-400">
                            <MapIcon size={40} />
                        </div>
                        <h3 className="text-lg font-bold text-slate-900 dark:text-white">No rooms configured</h3>
                        <p className="text-sm text-slate-500 mt-1 max-w-xs mx-auto">
                            Head over to the rooms table to add your hotel's inventory first.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
