import React, { useState } from 'react';
import { menuDescriptionAPI } from '../../services/api';
import { Sparkles, Loader2, RefreshCw, Check, ChevronDown } from 'lucide-react';
import toast from 'react-hot-toast';

const GenerateDescriptionButton = ({
    itemName,
    categoryName = null,
    price = null,
    currentDescription = '',
    onDescriptionGenerated,
    className = '',
}) => {
    const [loading, setLoading] = useState(false);
    const [showOptions, setShowOptions] = useState(false);
    const [alternatives, setAlternatives] = useState([]);
    const [showAlternatives, setShowAlternatives] = useState(false);

    const handleGenerate = async (options = {}) => {
        if (!itemName?.trim()) {
            toast.error('Please enter an item name first');
            return;
        }

        setLoading(true);
        setShowOptions(false);

        try {
            const response = await menuDescriptionAPI.generate(
                itemName,
                categoryName,
                price,
                options
            );

            if (response.data.success) {
                onDescriptionGenerated(response.data.description);
                toast.success('Description generated!');
            } else {
                toast.error(response.data.error || 'Failed to generate');
            }
        } catch (error) {
            toast.error('Failed to generate description');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleImprove = async () => {
        if (!currentDescription?.trim()) {
            toast.error('No description to improve');
            return;
        }

        setLoading(true);

        try {
            const response = await menuDescriptionAPI.improve(
                itemName,
                currentDescription,
                categoryName
            );

            if (response.data.success) {
                onDescriptionGenerated(response.data.description);
                toast.success('Description improved!');
            } else {
                toast.error(response.data.error || 'Failed to improve');
            }
        } catch (error) {
            toast.error('Failed to improve description');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleGetAlternatives = async () => {
        if (!itemName?.trim()) {
            toast.error('Please enter an item name first');
            return;
        }

        setLoading(true);

        try {
            const response = await menuDescriptionAPI.alternatives(
                itemName,
                categoryName,
                price,
                3
            );

            if (response.data.success && response.data.descriptions?.length > 0) {
                setAlternatives(response.data.descriptions);
                setShowAlternatives(true);
            } else {
                toast.error('No alternatives generated');
            }
        } catch (error) {
            toast.error('Failed to get alternatives');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const selectAlternative = (description) => {
        onDescriptionGenerated(description);
        setShowAlternatives(false);
        setAlternatives([]);
        toast.success('Description applied!');
    };

    return (
        <div className={`relative ${className}`}>
            {/* Main Button Group */}
            <div className="flex items-center gap-1">
                {/* Generate Button */}
                <button
                    type="button"
                    onClick={() => handleGenerate()}
                    disabled={loading || !itemName?.trim()}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-gradient-to-r from-purple-500 to-indigo-500 rounded-lg hover:from-purple-600 hover:to-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
                >
                    {loading ? (
                        <Loader2 className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                        <Sparkles className="w-3.5 h-3.5" />
                    )}
                    {loading ? 'Generating...' : 'AI Generate'}
                </button>

                {/* Dropdown Toggle */}
                <button
                    type="button"
                    onClick={() => setShowOptions(!showOptions)}
                    disabled={loading}
                    className="p-1.5 text-white bg-gradient-to-r from-purple-500 to-indigo-500 rounded-lg hover:from-purple-600 hover:to-indigo-600 disabled:opacity-50 transition-all"
                >
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform ${showOptions ? 'rotate-180' : ''}`} />
                </button>
            </div>

            {/* Options Dropdown */}
            {showOptions && (
                <>
                    <div
                        className="fixed inset-0 z-40"
                        onClick={() => setShowOptions(false)}
                    />
                    <div className="absolute right-0 top-full mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div className="p-1">
                            <button
                                type="button"
                                onClick={() => handleGenerate({ style: 'appetizing' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                🍽️ Appetizing style
                            </button>
                            <button
                                type="button"
                                onClick={() => handleGenerate({ style: 'formal' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                🎩 Formal style
                            </button>
                            <button
                                type="button"
                                onClick={() => handleGenerate({ style: 'casual' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                😊 Casual style
                            </button>
                            <button
                                type="button"
                                onClick={() => handleGenerate({ style: 'playful' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                🎉 Playful style
                            </button>

                            <hr className="my-1 border-gray-100" />

                            <button
                                type="button"
                                onClick={() => handleGenerate({ length: 'short' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                📝 Short (8-12 words)
                            </button>
                            <button
                                type="button"
                                onClick={() => handleGenerate({ length: 'long' })}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                            >
                                📄 Long (25-35 words)
                            </button>

                            <hr className="my-1 border-gray-100" />

                            <button
                                type="button"
                                onClick={handleGetAlternatives}
                                className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors flex items-center gap-2"
                            >
                                <RefreshCw className="w-3.5 h-3.5" />
                                Get 3 alternatives
                            </button>

                            {currentDescription && (
                                <button
                                    type="button"
                                    onClick={handleImprove}
                                    className="w-full text-left px-3 py-2 text-sm text-purple-600 hover:bg-purple-50 rounded-lg transition-colors flex items-center gap-2"
                                >
                                    <Sparkles className="w-3.5 h-3.5" />
                                    Improve current
                                </button>
                            )}
                        </div>
                    </div>
                </>
            )}

            {/* Alternatives Modal */}
            {showAlternatives && alternatives.length > 0 && (
                <>
                    <div
                        className="fixed inset-0 bg-black/20 z-50"
                        onClick={() => setShowAlternatives(false)}
                    />
                    <div className="absolute right-0 top-full mt-1 w-80 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 overflow-hidden">
                        <div className="p-3 border-b border-gray-100">
                            <h4 className="font-medium text-gray-800 text-sm flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-purple-500" />
                                Choose a description
                            </h4>
                        </div>
                        <div className="p-2 space-y-2 max-h-64 overflow-y-auto">
                            {alternatives.map((desc, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    onClick={() => selectAlternative(desc)}
                                    className="w-full text-left p-3 bg-gray-50 hover:bg-purple-50 rounded-lg transition-colors group"
                                >
                                    <p className="text-sm text-gray-700 leading-relaxed">{desc}</p>
                                    <span className="text-xs text-purple-500 opacity-0 group-hover:opacity-100 transition-opacity mt-1 inline-flex items-center gap-1">
                                        <Check className="w-3 h-3" />
                                        Click to apply
                                    </span>
                                </button>
                            ))}
                        </div>
                        <div className="p-2 border-t border-gray-100">
                            <button
                                type="button"
                                onClick={() => setShowAlternatives(false)}
                                className="w-full text-center text-xs text-gray-500 hover:text-gray-700 py-1"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};

export default GenerateDescriptionButton;
