import React, { useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import App from './App.jsx';
import { useBrandingStore } from './stores/brandingStore';
import { platformAPI } from './services/api';
import './index.css';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
            staleTime: 5 * 60 * 1000, // 5 minutes
        },
    },
});

function setFavicon(path) {
    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }
    link.href = path;
}

function BrandingLoader({ children }) {
    const { setBranding, loaded } = useBrandingStore();

    useEffect(() => {
        if (!loaded) {
            platformAPI.branding()
                .then((res) => {
                    const data = res.data.data;
                    setBranding(data);
                    // Set document title
                    if (data.platform_name) {
                        document.title = data.platform_name;
                    }
                    // Set favicon dynamically
                    if (data.platform_favicon) {
                        setFavicon('/storage/' + data.platform_favicon);
                    }
                })
                .catch(() => {}); // Use defaults on error
        }
    }, []);

    return children;
}

ReactDOM.createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <BrowserRouter>
                <BrandingLoader>
                    <App />
                </BrandingLoader>
                <Toaster
                    position="top-right"
                    toastOptions={{
                        duration: 3000,
                        style: {
                            background: '#1f2937',
                            color: '#f9fafb',
                        },
                    }}
                />
            </BrowserRouter>
        </QueryClientProvider>
    </React.StrictMode>
);
