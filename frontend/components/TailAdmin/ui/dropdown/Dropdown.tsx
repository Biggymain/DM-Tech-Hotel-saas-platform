import React from 'react';

interface DropdownProps {
  isOpen: boolean;
  onClose: () => void;
  className?: string;
  children: React.ReactNode;
}

export const Dropdown: React.FC<DropdownProps> = ({ isOpen, onClose, className, children }) => {
  if (!isOpen) return null;
  return (
    <div className={className}>
      {children}
    </div>
  );
};
