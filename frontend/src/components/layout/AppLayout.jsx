import React, { useState, useEffect } from 'react';
import Sidebar from './Sidebar';
import TopHeader from './TopHeader';

const AppLayout = ({ children, healthStatus }) => {
  const [isCollapsed, setIsCollapsed] = useState(false);

  // Thêm class danava-ui vào body để apply global styles của UI mới
  useEffect(() => {
    document.body.classList.add('danava-ui');
    return () => {
      document.body.classList.remove('danava-ui');
    };
  }, []);

  return (
    <div className="dn-layout-wrapper">
      <Sidebar isCollapsed={isCollapsed} healthStatus={healthStatus} />
      <div className="dn-main-area">
        <TopHeader 
          isCollapsed={isCollapsed} 
          toggleSidebar={() => setIsCollapsed(!isCollapsed)} 
        />
        <main className="dn-content-scroll">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AppLayout;
