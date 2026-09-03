import React from 'react';
import Link from 'next/link';

interface DropdownItemProps {
  onItemClick?: () => void;
  className?: string;
  tag?: string;
  href?: string;
  children: React.ReactNode;
}

export const DropdownItem: React.FC<DropdownItemProps> = ({ onItemClick, className, tag, href, children }) => {
  if (tag === 'a' && href) {
    return (
      <Link href={href} className={className} onClick={onItemClick}>
        {children}
      </Link>
    );
  }
  return (
    <div className={className} onClick={onItemClick}>
      {children}
    </div>
  );
};
