import React, { useState } from 'react';

const QueueMonitor = () => {
  const [activeTab, setActiveTab] = useState('All');

  const tabs = ['All', 'Waiting', 'Processing', 'Success', 'Failed', 'Cancelled'];

  const jobs = [
    { id: '#1042', campaign: 'DANAVA September', page: 'DANAVA Studio', post: 'Post #21', scheduled: '18:30 08/09/2026', attempts: 1, status: 'Waiting' },
    { id: '#1041', campaign: 'GLG Promo', page: 'GLG Yoga', post: 'Post #05', scheduled: '17:00 07/09/2026', attempts: 1, status: 'Processing' },
    { id: '#1040', campaign: 'Auto AI Daily', page: 'Tin Tức AI', post: 'News #88', scheduled: '15:00 07/09/2026', attempts: 1, status: 'Success' },
    { id: '#1039', campaign: 'VF Gym Welcome', page: 'VF Gym', post: 'Intro', scheduled: '12:00 07/09/2026', attempts: 3, status: 'Failed' },
  ];

  const getStatusColor = (status) => {
    switch(status) {
      case 'Waiting': return 'var(--dn-color-info)';
      case 'Processing': return 'var(--dn-color-primary)';
      case 'Success': return 'var(--dn-color-success)';
      case 'Failed': return 'var(--dn-color-danger)';
      case 'Cancelled': return 'var(--dn-text-tertiary)';
      default: return 'var(--dn-text-secondary)';
    }
  };

  return (
    <div className="dn-queue-monitor">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-6)' }}>
        <h2 style={{ margin: 0, fontSize: 'var(--dn-text-2xl)', color: 'var(--dn-text-primary)' }}>Queue Monitor</h2>
        <div style={{ display: 'flex', gap: 'var(--dn-space-2)' }}>
          <button className="dn-btn" style={{ border: '1px solid var(--dn-border-color)' }}>Pause Queue</button>
          <button className="dn-btn dn-btn-primary">Refresh</button>
        </div>
      </div>

      {/* Top Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 'var(--dn-space-4)', marginBottom: 'var(--dn-space-6)' }}>
        {[
          { label: 'Waiting', value: '30' },
          { label: 'Processing', value: '4' },
          { label: 'Success', value: '358' },
          { label: 'Failed', value: '2' },
        ].map((card, index) => (
          <div key={index} style={{ 
            backgroundColor: 'var(--dn-bg-surface)', 
            padding: 'var(--dn-space-4)', 
            borderRadius: 'var(--dn-radius-lg)', 
            border: '1px solid var(--dn-border-color)',
            textAlign: 'center'
          }}>
            <div style={{ color: 'var(--dn-text-secondary)', fontSize: 'var(--dn-text-sm)', marginBottom: 'var(--dn-space-2)' }}>
              {card.label}
            </div>
            <div style={{ fontSize: 'var(--dn-text-2xl)', fontWeight: 'bold', color: 'var(--dn-text-primary)' }}>
              {card.value}
            </div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', borderBottom: '1px solid var(--dn-border-color)', marginBottom: 'var(--dn-space-4)' }}>
        {tabs.map(tab => (
          <div 
            key={tab} 
            onClick={() => setActiveTab(tab)}
            style={{ 
              padding: 'var(--dn-space-2) var(--dn-space-4)', 
              cursor: 'pointer',
              borderBottom: activeTab === tab ? '2px solid var(--dn-color-primary)' : '2px solid transparent',
              color: activeTab === tab ? 'var(--dn-color-primary)' : 'var(--dn-text-secondary)',
              fontWeight: activeTab === tab ? '600' : '400'
            }}
          >
            {tab}
          </div>
        ))}
      </div>

      {/* Table */}
      <div style={{ backgroundColor: 'var(--dn-bg-surface)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)', overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead style={{ backgroundColor: 'var(--dn-bg-surface-hover)', borderBottom: '1px solid var(--dn-border-color)' }}>
            <tr>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Job ID</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Campaign</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Page</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Post</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Scheduled</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Attempts</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Status</th>
              <th style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500', color: 'var(--dn-text-secondary)' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {jobs.map((job, idx) => (
              <tr key={idx} style={{ borderBottom: '1px solid var(--dn-border-color)' }}>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)' }}>{job.id}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)' }}>{job.campaign}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', fontWeight: '500' }}>{job.page}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)' }}>{job.post}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', color: 'var(--dn-text-secondary)' }}>{job.scheduled}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)', textAlign: 'center' }}>{job.attempts}</td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)' }}>
                  <span style={{ 
                    display: 'inline-block', 
                    padding: '2px 8px', 
                    borderRadius: '12px', 
                    fontSize: '0.75rem', 
                    fontWeight: 'bold',
                    backgroundColor: `${getStatusColor(job.status)}20`,
                    color: getStatusColor(job.status)
                  }}>
                    {job.status}
                  </span>
                </td>
                <td style={{ padding: 'var(--dn-space-3) var(--dn-space-4)' }}>
                  <button className="dn-btn" style={{ padding: '4px 8px', fontSize: '0.75rem', border: '1px solid var(--dn-border-color)' }}>
                    {job.status === 'Failed' ? 'Retry' : 'View'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default QueueMonitor;
