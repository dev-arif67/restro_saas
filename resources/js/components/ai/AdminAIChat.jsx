import React, { useState, useRef, useEffect } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { aiAPI } from '../../services/api';
import {
    HiOutlineSparkles,
    HiOutlineXMark,
    HiOutlinePaperAirplane,
    HiOutlineLightBulb,
    HiOutlineChartBarSquare,
    HiOutlineArrowPath,
    HiOutlineTrash,
    HiOutlineChatBubbleLeftRight,
} from 'react-icons/hi2';
import toast from 'react-hot-toast';

export default function AdminAIChat() {
    const [isOpen, setIsOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [messages, setMessages] = useState([]);
    const [conversationId, setConversationId] = useState(null);
    const [showSuggestions, setShowSuggestions] = useState(true);
    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    // Fetch suggestions
    const { data: suggestionsData } = useQuery({
        queryKey: ['ai-suggestions'],
        queryFn: async () => {
            const res = await aiAPI.suggestions();
            return res.data.data;
        },
        enabled: isOpen,
        staleTime: 1000 * 60 * 30, // 30 minutes
    });

    // Ask mutation
    const askMutation = useMutation({
        mutationFn: async ({ question }) => {
            const res = await aiAPI.ask(question, conversationId);
            return res.data.data;
        },
        onSuccess: (data) => {
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: data.answer, chart: data.chart },
            ]);
            setConversationId(data.conversation_id);
            setShowSuggestions(false);
        },
        onError: (err) => {
            const errorMsg = err.response?.data?.message || 'Failed to get response';
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: `Sorry, ${errorMsg}`, isError: true },
            ]);
            toast.error(errorMsg);
        },
    });

    // Scroll to bottom when messages change
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    // Focus input when opened
    useEffect(() => {
        if (isOpen) {
            setTimeout(() => inputRef.current?.focus(), 100);
        }
    }, [isOpen]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!message.trim() || askMutation.isPending) return;

        const question = message.trim();
        setMessages((prev) => [...prev, { role: 'user', content: question }]);
        setMessage('');
        askMutation.mutate({ question });
    };

    const handleSuggestionClick = (question) => {
        setMessages((prev) => [...prev, { role: 'user', content: question }]);
        askMutation.mutate({ question });
    };

    const handleNewChat = () => {
        setMessages([]);
        setConversationId(null);
        setShowSuggestions(true);
    };

    const renderChart = (chart) => {
        if (!chart || !chart.data || chart.data.length === 0) return null;

        // Simple visualization - can be enhanced with a charting library
        const maxValue = Math.max(...chart.data.map((d) => d.revenue || d.orders || d.quantity_sold || 0));

        return (
            <div className="mt-3 p-3 bg-gray-50 rounded-lg">
                <p className="text-xs font-medium text-gray-500 mb-2">{chart.title}</p>
                <div className="space-y-1.5">
                    {chart.data.slice(0, 5).map((item, i) => {
                        const value = item.revenue || item.orders || item.quantity_sold || 0;
                        const percentage = maxValue > 0 ? (value / maxValue) * 100 : 0;
                        const label = item.name || item.date || item.hour || item.category || `Item ${i + 1}`;

                        return (
                            <div key={i} className="flex items-center gap-2 text-xs">
                                <span className="w-20 truncate text-gray-600">{label}</span>
                                <div className="flex-1 h-4 bg-gray-200 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-orange-500 rounded-full transition-all"
                                        style={{ width: `${percentage}%` }}
                                    />
                                </div>
                                <span className="w-16 text-right text-gray-700 font-medium">
                                    {typeof value === 'number' && value > 100
                                        ? `৳${value.toLocaleString()}`
                                        : value}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    };

    return (
        <>
            {/* Floating Button */}
            <button
                onClick={() => setIsOpen(!isOpen)}
                className={`fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 ${
                    isOpen ? 'bg-gray-700 rotate-90' : 'bg-orange-500 hover:bg-orange-600'
                }`}
                title="AI Analytics Assistant"
            >
                {isOpen ? (
                    <HiOutlineXMark className="w-6 h-6 text-white" />
                ) : (
                    <HiOutlineSparkles className="w-6 h-6 text-white" />
                )}
            </button>

            {/* Chat Panel */}
            {isOpen && (
                <div className="fixed bottom-24 right-6 z-50 w-96 max-w-[calc(100vw-3rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden animate-in slide-in-from-bottom-4 duration-300">
                    {/* Header */}
                    <div className="bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <HiOutlineSparkles className="w-5 h-5 text-white" />
                            <h3 className="text-white font-semibold">AI Analytics Assistant</h3>
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                onClick={handleNewChat}
                                className="p-1.5 hover:bg-white/20 rounded-lg transition-colors"
                                title="New conversation"
                            >
                                <HiOutlineArrowPath className="w-4 h-4 text-white" />
                            </button>
                        </div>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-4 max-h-96 min-h-[300px]">
                        {messages.length === 0 && showSuggestions && (
                            <div className="space-y-4">
                                <div className="text-center py-4">
                                    <div className="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <HiOutlineLightBulb className="w-6 h-6 text-orange-600" />
                                    </div>
                                    <p className="text-gray-600 text-sm">
                                        Ask me anything about your restaurant's performance!
                                    </p>
                                </div>

                                {suggestionsData && (
                                    <div className="space-y-3">
                                        {suggestionsData.slice(0, 2).map((category, i) => (
                                            <div key={i}>
                                                <p className="text-xs font-medium text-gray-400 uppercase mb-1.5">
                                                    {category.category}
                                                </p>
                                                <div className="space-y-1.5">
                                                    {category.questions.slice(0, 2).map((q, j) => (
                                                        <button
                                                            key={j}
                                                            onClick={() => handleSuggestionClick(q)}
                                                            className="w-full text-left text-sm px-3 py-2 bg-gray-50 hover:bg-orange-50 rounded-lg text-gray-700 hover:text-orange-700 transition-colors"
                                                        >
                                                            {q}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[85%] rounded-2xl px-4 py-2.5 ${
                                        msg.role === 'user'
                                            ? 'bg-orange-500 text-white rounded-br-md'
                                            : msg.isError
                                            ? 'bg-red-50 text-red-700 rounded-bl-md'
                                            : 'bg-gray-100 text-gray-800 rounded-bl-md'
                                    }`}
                                >
                                    <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
                                    {msg.chart && renderChart(msg.chart)}
                                </div>
                            </div>
                        ))}

                        {askMutation.isPending && (
                            <div className="flex justify-start">
                                <div className="bg-gray-100 rounded-2xl rounded-bl-md px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex gap-1">
                                            <span className="w-2 h-2 bg-orange-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                            <span className="w-2 h-2 bg-orange-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                            <span className="w-2 h-2 bg-orange-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                                        </div>
                                        <span className="text-xs text-gray-500">Analyzing...</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    {/* Input */}
                    <form onSubmit={handleSubmit} className="p-3 border-t border-gray-100">
                        <div className="flex items-center gap-2">
                            <input
                                ref={inputRef}
                                type="text"
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                placeholder="Ask about your sales, orders, menu..."
                                className="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                disabled={askMutation.isPending}
                            />
                            <button
                                type="submit"
                                disabled={!message.trim() || askMutation.isPending}
                                className="p-2.5 bg-orange-500 text-white rounded-xl hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <HiOutlinePaperAirplane className="w-5 h-5" />
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
