import React from 'react';

export const ThemeToggleButton = () => {
  return (
    <button aria-label="Toggle Theme" className="w-10 h-10 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-800">
      <span className="text-gray-500">☼</span>
    </button>
  );
};
