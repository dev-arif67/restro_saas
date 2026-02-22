import React, { useState, useEffect } from 'react';
import { sentimentAPI } from '../../services/api';

const SentimentDashboard = () => {
    const [overview, setOverview] = useState(null);
    const [trends, setTrends] = useState([]);
    const [negativeFeedback, setNegativeFeedback] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('overview');
    const [period, setPeriod] = useState('30d');

    useEffect(() => {
        fetchData();
    }, [period]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const [overviewRes, trendsRes, negativeRes] = await Promise.all([
                sentimentAPI.getOverview(period),
                sentimentAPI.getTrends(period === '7d' ? 7 : period === '14d' ? 14 : 30),
                sentimentAPI.getNegativeFeedback(10),
            ]);

            if (overviewRes.data.success) {
                setOverview(overviewRes.data);
            }
            if (trendsRes.data.success) {
                setTrends(trendsRes.data.trends || []);
            }
            if (negativeRes.data.success) {
                setNegativeFeedback(negativeRes.data.feedback || []);
            }
        } catch (error) {
            console.error('Failed to fetch sentiment data:', error);
        } finally {
            setLoading(false);
        }
    };

    const getSentimentColor = (sentiment) => {
        switch (sentiment) {
            case 'positive':
                return '#10B981';
            case 'negative':
                return '#EF4444';
            default:
                return '#6B7280';
        }
    };

    const getSentimentEmoji = (score) => {
        if (score >= 0.7) return '😊';
        if (score >= 0.4) return '😐';
        return '😞';
    };

    if (loading) {
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div className="animate-pulse">
                    <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                    <div className="grid grid-cols-4 gap-4">
                        {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="h-20 bg-gray-200 rounded"></div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <svg className="w-5 h-5 text-[#ED802A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 className="font-semibold text-gray-800">Customer Sentiment Analysis</h3>
                </div>

                {/* Period Selector */}
                <div className="flex items-center gap-2">
                    {['7d', '14d', '30d'].map((p) => (
                        <button
                            key={p}
                            onClick={() => setPeriod(p)}
                            className={`px-3 py-1 rounded-lg text-sm transition-colors ${
                                period === p
                                    ? 'bg-[#ED802A] text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {p === '7d' ? '7 Days' : p === '14d' ? '14 Days' : '30 Days'}
                        </button>
                    ))}
                </div>
            </div>

            {/* Tabs */}
            <div className="px-6 border-b border-gray-200">
                <div className="flex gap-4">
                    {['overview', 'trends', 'issues'].map((tab) => (
                        <button
                            key={tab}
                            onClick={() => setActiveTab(tab)}
                            className={`py-3 px-1 border-b-2 -mb-px transition-colors ${
                                activeTab === tab
                                    ? 'border-[#ED802A] text-[#ED802A]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            {tab === 'overview' && 'Overview'}
                            {tab === 'trends' && 'Trends'}
                            {tab === 'issues' && 'Issues'}
                        </button>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div className="p-6">
                {activeTab === 'overview' && overview && (
                    <div className="space-y-6">
                        {/* Summary Stats */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="bg-gray-50 rounded-lg p-4 text-center">
                                <div className="text-3xl mb-1">
                                    {getSentimentEmoji(overview.summary?.average_score || 0.5)}
                                </div>
                                <div className="text-2xl font-bold text-gray-800">
                                    {overview.summary?.average_score?.toFixed(2) || '0.00'}
                                </div>
                                <div className="text-sm text-gray-500">Avg. Score</div>
                            </div>

                            <div className="bg-green-50 rounded-lg p-4 text-center">
                                <div className="text-2xl font-bold text-green-600">
                                    {overview.summary?.positive_percent || 0}%
                                </div>
                                <div className="text-sm text-green-600">
                                    {overview.summary?.positive || 0} Positive
                                </div>
                            </div>

                            <div className="bg-gray-50 rounded-lg p-4 text-center">
                                <div className="text-2xl font-bold text-gray-600">
                                    {overview.summary?.neutral || 0}
                                </div>
                                <div className="text-sm text-gray-500">Neutral</div>
                            </div>

                            <div className="bg-red-50 rounded-lg p-4 text-center">
                                <div className="text-2xl font-bold text-red-600">
                                    {overview.summary?.negative_percent || 0}%
                                </div>
                                <div className="text-sm text-red-600">
                                    {overview.summary?.negative || 0} Negative
                                </div>
                            </div>
                        </div>

                        {/* AI Insights */}
                        {overview.insights && (
                            <div className="bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg p-4 border border-amber-200">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-full bg-[#ED802A] flex items-center justify-center flex-shrink-0">
                                        <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div className="prose prose-sm max-w-none">
                                        {overview.insights.split('\n\n').map((paragraph, idx) => (
                                            <p key={idx} className="text-gray-700 mb-2">
                                                {paragraph}
                                            </p>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Common Themes */}
                        {overview.common_themes?.length > 0 && (
                            <div>
                                <h4 className="font-medium text-gray-700 mb-3">Common Themes</h4>
                                <div className="flex flex-wrap gap-2">
                                    {overview.common_themes.map((theme, idx) => (
                                        <span
                                            key={idx}
                                            className="px-3 py-1 rounded-full text-sm border"
                                            style={{
                                                backgroundColor: getSentimentColor(theme.sentiment) + '20',
                                                borderColor: getSentimentColor(theme.sentiment),
                                                color: getSentimentColor(theme.sentiment),
                                            }}
                                        >
                                            {theme.word} ({theme.count})
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* No Data */}
                        {overview.summary?.total_analyzed === 0 && (
                            <div className="text-center py-8 text-gray-500">
                                <svg className="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p>No customer feedback found for this period.</p>
                                <p className="text-sm">Feedback is collected from order notes.</p>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'trends' && (
                    <div className="space-y-4">
                        {trends.length > 0 ? (
                            <>
                                {/* Mini Chart */}
                                <div className="h-48 flex items-end justify-between gap-1">
                                    {trends.map((day, idx) => {
                                        const total = day.positive + day.neutral + day.negative;
                                        const maxHeight = 150;
                                        const scale = total > 0 ? maxHeight / Math.max(...trends.map(t => t.positive + t.neutral + t.negative)) : 0;

                                        return (
                                            <div key={idx} className="flex-1 flex flex-col items-center">
                                                {total > 0 ? (
                                                    <div className="w-full flex flex-col rounded-t overflow-hidden" style={{ height: total * scale }}>
                                                        {day.negative > 0 && (
                                                            <div
                                                                className="bg-red-400"
                                                                style={{ height: `${(day.negative / total) * 100}%` }}
                                                                title={`Negative: ${day.negative}`}
                                                            />
                                                        )}
                                                        {day.neutral > 0 && (
                                                            <div
                                                                className="bg-gray-400"
                                                                style={{ height: `${(day.neutral / total) * 100}%` }}
                                                                title={`Neutral: ${day.neutral}`}
                                                            />
                                                        )}
                                                        {day.positive > 0 && (
                                                            <div
                                                                className="bg-green-400"
                                                                style={{ height: `${(day.positive / total) * 100}%` }}
                                                                title={`Positive: ${day.positive}`}
                                                            />
                                                        )}
                                                    </div>
                                                ) : (
                                                    <div className="w-full h-2 bg-gray-100 rounded"></div>
                                                )}
                                                <div className="text-xs text-gray-400 mt-2 transform -rotate-45 origin-top-left">
                                                    {new Date(day.date).toLocaleDateString('en', { month: 'short', day: 'numeric' })}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* Legend */}
                                <div className="flex justify-center gap-6 pt-4 border-t">
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded bg-green-400"></div>
                                        <span className="text-sm text-gray-600">Positive</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded bg-gray-400"></div>
                                        <span className="text-sm text-gray-600">Neutral</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded bg-red-400"></div>
                                        <span className="text-sm text-gray-600">Negative</span>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                No trend data available yet.
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'issues' && (
                    <div className="space-y-3">
                        {negativeFeedback.length > 0 ? (
                            <>
                                <p className="text-sm text-gray-500 mb-4">
                                    Recent negative feedback requiring attention:
                                </p>
                                {negativeFeedback.map((item, idx) => (
                                    <div
                                        key={idx}
                                        className="bg-red-50 border border-red-200 rounded-lg p-4"
                                    >
                                        <div className="flex justify-between items-start mb-2">
                                            <span className="text-sm font-medium text-red-700">
                                                Order #{item.order_number}
                                            </span>
                                            <span className="text-xs text-gray-500">{item.date}</span>
                                        </div>
                                        <p className="text-gray-700 text-sm mb-2">
                                            "{item.feedback}"
                                        </p>
                                        {item.items?.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {item.items.map((itemName, i) => (
                                                    <span
                                                        key={i}
                                                        className="px-2 py-0.5 bg-white rounded text-xs text-gray-600"
                                                    >
                                                        {itemName}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        <div className="mt-2 flex items-center gap-2">
                                            <span className="text-xs text-red-600">
                                                Score: {item.score?.toFixed(2)}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <svg className="w-12 h-12 mx-auto mb-3 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p className="text-green-600 font-medium">No negative feedback!</p>
                                <p className="text-sm">Great job keeping customers happy.</p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

export default SentimentDashboard;
