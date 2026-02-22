import React, { useState, useEffect } from 'react';
import { forecastAPI } from '../../services/api';
import {
    TrendingUp,
    TrendingDown,
    Minus,
    Calendar,
    Clock,
    Users,
    RefreshCw,
    ChevronDown,
    ChevronUp,
    Sparkles,
    AlertCircle,
} from 'lucide-react';

const ForecastWidget = () => {
    const [forecast, setForecast] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        loadForecast();
    }, []);

    const loadForecast = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await forecastAPI.getForecast(7);
            setForecast(response.data);
        } catch (err) {
            setError(err.response?.data?.error || 'Failed to load forecast');
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount).replace('BDT', '৳');
    };

    const getTrendIcon = (trend) => {
        switch (trend) {
            case 'up':
                return <TrendingUp className="w-4 h-4 text-green-500" />;
            case 'down':
                return <TrendingDown className="w-4 h-4 text-red-500" />;
            default:
                return <Minus className="w-4 h-4 text-gray-500" />;
        }
    };

    const getConfidenceColor = (confidence) => {
        if (confidence >= 80) return 'text-green-600 bg-green-100';
        if (confidence >= 60) return 'text-yellow-600 bg-yellow-100';
        return 'text-red-600 bg-red-100';
    };

    const getDayColor = (dayName) => {
        const colors = {
            Sunday: 'bg-red-100 text-red-700',
            Monday: 'bg-blue-100 text-blue-700',
            Tuesday: 'bg-purple-100 text-purple-700',
            Wednesday: 'bg-green-100 text-green-700',
            Thursday: 'bg-yellow-100 text-yellow-700',
            Friday: 'bg-orange-100 text-orange-700',
            Saturday: 'bg-pink-100 text-pink-700',
        };
        return colors[dayName] || 'bg-gray-100 text-gray-700';
    };

    if (loading) {
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div className="animate-pulse">
                    <div className="flex items-center justify-between mb-4">
                        <div className="h-6 bg-gray-200 rounded w-40"></div>
                        <div className="h-6 bg-gray-200 rounded w-16"></div>
                    </div>
                    <div className="space-y-3">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="h-12 bg-gray-100 rounded"></div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 bg-orange-100 rounded-lg">
                        <Sparkles className="w-5 h-5 text-orange-600" />
                    </div>
                    <h3 className="text-lg font-semibold text-gray-800">Sales Forecast</h3>
                </div>
                <div className="flex items-center gap-2 text-amber-600 bg-amber-50 p-3 rounded-lg">
                    <AlertCircle className="w-5 h-5" />
                    <span className="text-sm">{error}</span>
                </div>
                <button
                    onClick={loadForecast}
                    className="mt-4 text-sm text-[#ED802A] hover:underline flex items-center gap-1"
                >
                    <RefreshCw className="w-4 h-4" />
                    Try again
                </button>
            </div>
        );
    }

    if (!forecast) return null;

    const { summary, forecast: dailyForecast, busy_hours, insights } = forecast;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-gradient-to-br from-orange-100 to-orange-200 rounded-lg">
                        <Sparkles className="w-5 h-5 text-[#ED802A]" />
                    </div>
                    <div>
                        <h3 className="text-lg font-semibold text-gray-800">7-Day Forecast</h3>
                        <p className="text-xs text-gray-500">AI-powered sales prediction</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span className={`text-xs px-2 py-1 rounded-full ${getConfidenceColor(summary.confidence)}`}>
                        {summary.confidence}% confidence
                    </span>
                    <button
                        onClick={loadForecast}
                        className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        title="Refresh forecast"
                    >
                        <RefreshCw className="w-4 h-4" />
                    </button>
                </div>
            </div>

            {/* Summary Stats */}
            <div className="grid grid-cols-3 divide-x divide-gray-100 bg-gray-50">
                <div className="px-4 py-3 text-center">
                    <p className="text-xs text-gray-500 mb-1">Predicted Revenue</p>
                    <p className="text-lg font-bold text-gray-800">{formatCurrency(summary.predicted_revenue)}</p>
                </div>
                <div className="px-4 py-3 text-center">
                    <p className="text-xs text-gray-500 mb-1">Expected Orders</p>
                    <p className="text-lg font-bold text-gray-800">{summary.predicted_orders}</p>
                </div>
                <div className="px-4 py-3 text-center">
                    <p className="text-xs text-gray-500 mb-1">Trend</p>
                    <div className="flex items-center justify-center gap-1">
                        {getTrendIcon(summary.trend)}
                        <span className={`font-bold ${
                            summary.trend === 'up' ? 'text-green-600' :
                            summary.trend === 'down' ? 'text-red-600' : 'text-gray-600'
                        }`}>
                            {summary.trend === 'up' ? 'Growing' :
                             summary.trend === 'down' ? 'Declining' : 'Stable'}
                        </span>
                    </div>
                </div>
            </div>

            {/* Daily Forecast */}
            <div className="p-4">
                <div className="flex items-center gap-2 mb-3">
                    <Calendar className="w-4 h-4 text-gray-400" />
                    <span className="text-sm font-medium text-gray-600">Daily Breakdown</span>
                </div>
                <div className="space-y-2">
                    {dailyForecast.slice(0, expanded ? 7 : 3).map((day, index) => (
                        <div
                            key={day.date}
                            className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <div className="flex items-center gap-3">
                                <span className={`text-xs font-medium px-2 py-1 rounded ${getDayColor(day.day_name)}`}>
                                    {day.day_name.slice(0, 3)}
                                </span>
                                <span className="text-sm text-gray-600">
                                    {new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                                </span>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-gray-800">
                                        {formatCurrency(day.predicted_revenue)}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        ~{day.predicted_orders} orders
                                    </p>
                                </div>
                                <div
                                    className={`text-xs px-1.5 py-0.5 rounded ${getConfidenceColor(day.confidence)}`}
                                >
                                    {day.confidence}%
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {dailyForecast.length > 3 && (
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="mt-3 w-full text-center text-sm text-[#ED802A] hover:text-orange-700 flex items-center justify-center gap-1"
                    >
                        {expanded ? (
                            <>
                                <ChevronUp className="w-4 h-4" />
                                Show less
                            </>
                        ) : (
                            <>
                                <ChevronDown className="w-4 h-4" />
                                Show all {dailyForecast.length} days
                            </>
                        )}
                    </button>
                )}
            </div>

            {/* Peak Hours */}
            {busy_hours && busy_hours.length > 0 && (
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2 mb-3">
                        <Clock className="w-4 h-4 text-gray-400" />
                        <span className="text-sm font-medium text-gray-600">Peak Hours</span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {busy_hours.slice(0, 4).map((hourData, index) => (
                            <span
                                key={index}
                                className="px-3 py-1.5 bg-orange-50 text-[#ED802A] rounded-full text-sm font-medium"
                            >
                                {hourData.hour || hourData}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* AI Insights */}
            {insights && (
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2 mb-3">
                        <Sparkles className="w-4 h-4 text-gray-400" />
                        <span className="text-sm font-medium text-gray-600">AI Insights</span>
                    </div>
                    <div className="bg-gradient-to-r from-orange-50 to-amber-50 rounded-lg p-4 border border-orange-100">
                        <div
                            className="text-sm text-gray-700 prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{
                                __html: insights
                                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                                    .replace(/\n\n/g, '<br/><br/>')
                            }}
                        />
                    </div>
                </div>
            )}

            {/* Footer */}
            {forecast.cached && (
                <div className="px-4 pb-3 text-center">
                    <span className="text-xs text-gray-400">
                        Cached forecast • Updated {new Date(forecast.generated_at).toLocaleTimeString()}
                    </span>
                </div>
            )}
        </div>
    );
};

export default ForecastWidget;
