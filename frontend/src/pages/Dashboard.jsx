import React, { useState, useEffect } from 'react';
import { getDashboardStats } from '../api';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

const Dashboard = () => {
  const [stats, setStats] = useState({
    total_pages: 0,
    queued_posts: 0,
    published_today: 0,
    failed_posts: 0,
    active_campaigns: 0,
    activity: [],
    chart_data: []
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadStats();
  }, []);

  const loadStats = async () => {
    setLoading(true);
    try {
      const data = await getDashboardStats();
      setStats(data.data || data); // handle standard or wrapped responses
    } catch (err) {
      console.error("Failed to load dashboard stats", err);
    } finally {
      setLoading(false);
    }
  };

  const cards = [
    { title: 'Trang kết nối', value: stats.total_pages, icon: '📱', color: 'var(--dn-color-primary)' },
    { title: 'Chiến dịch (Active)', value: stats.active_campaigns, icon: '🚀', color: 'var(--dn-color-success)' },
    { title: 'Bài chờ đăng', value: stats.queued_posts, icon: '⏱️', color: 'var(--dn-color-warning)' },
    { title: 'Lỗi đồng bộ', value: stats.failed_posts, icon: '⚠️', color: 'var(--dn-color-danger)' },
  ];

  return (
    <div className="dn-dashboard">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-6)' }}>
        <h2 style={{ margin: 0, fontSize: 'var(--dn-text-2xl)', color: 'var(--dn-text-primary)' }}>Tổng Quan Hệ Thống</h2>
        <button onClick={loadStats} className="dn-btn" style={{ border: '1px solid var(--dn-border-color)' }}>
          {loading ? 'Đang tải...' : '↻ Làm mới'}
        </button>
      </div>

      {/* Metrics Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--dn-space-6)', marginBottom: 'var(--dn-space-8)' }}>
        {cards.map((card, idx) => (
          <div key={idx} style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-6)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)', display: 'flex', alignItems: 'center', gap: 'var(--dn-space-4)', boxShadow: 'var(--dn-shadow-sm)', transition: 'transform var(--dn-transition-fast)' }} onMouseOver={e => e.currentTarget.style.transform = 'translateY(-2px)'} onMouseOut={e => e.currentTarget.style.transform = 'none'}>
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', backgroundColor: 'var(--dn-bg-app)', display: 'flex', justifyContent: 'center', alignItems: 'center', fontSize: '1.5rem', color: card.color }}>
              {card.icon}
            </div>
            <div>
              <div style={{ color: 'var(--dn-text-secondary)', fontSize: 'var(--dn-text-sm)', marginBottom: '4px' }}>{card.title}</div>
              <div style={{ color: 'var(--dn-text-primary)', fontSize: 'var(--dn-text-2xl)', fontWeight: 'bold' }}>{card.value}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Main Content Area */}
      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 'var(--dn-space-6)' }}>
        
        {/* Chart Section */}
        <div style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-6)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)' }}>
          <h3 style={{ margin: '0 0 var(--dn-space-6) 0', color: 'var(--dn-text-primary)' }}>Hiệu suất bài đăng (7 ngày qua)</h3>
          
          <div style={{ width: '100%', height: '300px' }}>
            {stats.chart_data && stats.chart_data.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={stats.chart_data} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--dn-border-color)" vertical={false} />
                  <XAxis dataKey="name" stroke="var(--dn-text-secondary)" tick={{fill: 'var(--dn-text-secondary)'}} />
                  <YAxis stroke="var(--dn-text-secondary)" tick={{fill: 'var(--dn-text-secondary)'}} />
                  <Tooltip 
                    contentStyle={{ backgroundColor: 'var(--dn-bg-app)', border: '1px solid var(--dn-border-color)', borderRadius: '8px' }} 
                    itemStyle={{ color: 'var(--dn-text-primary)' }} 
                  />
                  <Bar dataKey="success" name="Thành công" fill="var(--dn-color-success)" radius={[4, 4, 0, 0]} maxBarSize={40} />
                  <Bar dataKey="failed" name="Thất bại" fill="var(--dn-color-danger)" radius={[4, 4, 0, 0]} maxBarSize={40} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div style={{ height: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center', color: 'var(--dn-text-secondary)' }}>
                Chưa có dữ liệu
              </div>
            )}
          </div>
        </div>

        {/* Activity Timeline Section */}
        <div style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-6)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)' }}>
          <h3 style={{ margin: '0 0 var(--dn-space-6) 0', color: 'var(--dn-text-primary)' }}>Lịch sử hoạt động</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--dn-space-4)' }}>
            
            {stats.activity && stats.activity.length > 0 ? stats.activity.map((item, idx) => (
              <div key={idx} style={{ display: 'flex', gap: 'var(--dn-space-4)' }}>
                <div style={{ color: 'var(--dn-text-tertiary)', fontSize: 'var(--dn-text-sm)', width: '45px', textAlign: 'right' }}>
                  {item.time}
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                  <div style={{ width: '10px', height: '10px', borderRadius: '50%', backgroundColor: item.color, marginTop: '5px' }}></div>
                  {idx !== stats.activity.length - 1 && <div style={{ width: '2px', flex: 1, backgroundColor: 'var(--dn-border-color)', margin: '4px 0' }}></div>}
                </div>
                <div style={{ paddingBottom: 'var(--dn-space-4)' }}>
                  <div style={{ color: 'var(--dn-text-primary)', fontWeight: '500' }}>{item.name}</div>
                  <div style={{ color: item.color, fontSize: 'var(--dn-text-xs)', marginTop: '2px', fontWeight: 'bold' }}>{item.action}</div>
                </div>
              </div>
            )) : (
              <div style={{ color: 'var(--dn-text-secondary)', textAlign: 'center', padding: '20px' }}>Không có hoạt động gần đây</div>
            )}

          </div>
        </div>

      </div>
    </div>
  );
};

export default Dashboard;
