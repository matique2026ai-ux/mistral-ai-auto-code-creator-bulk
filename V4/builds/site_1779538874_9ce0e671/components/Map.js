import React from 'react';

const Map = () => {
  return (
    <div className="w-full h-[500px] bg-gray-800 rounded-xl flex items-center justify-center">
      <div className="text-center text-text-secondary">
        <svg className="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p className="text-lg font-medium">48 Rue de la Gastronomie</p>
        <p className="text-sm opacity-75">75001 Paris, France</p>
      </div>
    </div>
  );
};

export default Map;
