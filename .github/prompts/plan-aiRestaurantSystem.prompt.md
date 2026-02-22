## Plan: AI-Powered Restaurant SaaS Platform

**TL;DR:** Integrate Google Gemini AI across your platform with 6 key features: Admin Analytics Assistant & Sales Forecasting first, then Customer Chatbot, Smart Recommendations, Auto Menu Descriptions, and Sentiment Analysis. The architecture uses a central `AIService` that abstracts the AI provider, making it easy to swap providers later. Each feature gets its own specialized service that leverages the core AI service.

---

### **Steps**

**Phase 1: Core AI Infrastructure**

1. **Create AI Configuration** - Add `config/ai.php` with Gemini API settings, rate limits, and feature toggles per tenant

2. **Create AI Service Layer** - Build [app/Services/AI/GeminiService.php](app/Services/AI/GeminiService.php) as the core API wrapper with:
   - Chat completion method
   - Embedding generation (for recommendations)
   - Rate limiting per tenant
   - Response caching for repeated queries
   - Fallback handling when quota exceeded

3. **Add AI Migrations** - Create tables for:
   - `ai_conversations` (user_id, tenant_id, context, messages JSON, created_at)
   - `ai_usage_logs` (tenant_id, feature, tokens_used, cost, created_at)
   - `menu_item_embeddings` (menu_item_id, embedding vector, updated_at)

4. **Environment Setup** - Add `GEMINI_API_KEY` to `.env.example`, install `google/generative-ai` package

---

**Phase 2: Admin Analytics Assistant (Priority)**

5. **Create Analytics AI Service** - Build [app/Services/AI/AnalyticsAssistantService.php](app/Services/AI/AnalyticsAssistantService.php) that:
   - Converts natural language to database queries
   - Understands context: "sales last week", "top items this month", "compare revenue"
   - Uses existing `ReportController` data methods internally
   - Returns structured responses with charts data

6. **Create Analytics AI Controller** - Add [app/Http/Controllers/Api/AIAnalyticsController.php](app/Http/Controllers/Api/AIAnalyticsController.php) with:
   - `POST /api/ai/analytics/ask` - Natural language query endpoint
   - `GET /api/ai/analytics/suggestions` - Proactive insights

7. **Build Admin AI Chat Component** - Create [resources/js/components/ai/AdminAIChat.jsx](resources/js/components/ai/AdminAIChat.jsx):
   - Floating chat widget on dashboard
   - Query history
   - Auto-generated chart visualizations from responses
   - Example prompts: "What are my best selling items?", "Show revenue trend"

---

**Phase 3: Sales Forecasting**

8. **Create Forecasting Service** - Build [app/Services/AI/SalesForecastService.php](app/Services/AI/SalesForecastService.php) that:
   - Analyzes historical order data patterns
   - Predicts daily/weekly revenue
   - Identifies busy hours by day of week
   - Suggests optimal staffing/inventory

9. **Add Forecasting Dashboard Widget** - Extend [AdminDashboardPage.jsx](resources/js/pages/admin/AdminDashboardPage.jsx) with:
   - "AI Predictions" card showing next 7 days forecast
   - Confidence indicators
   - Refresh on demand

---

**Phase 4: Customer Chatbot**

10. **Create Customer AI Service** - Build [app/Services/AI/CustomerChatService.php](app/Services/AI/CustomerChatService.php) with:
    - Menu Q&A (ingredients, allergens, recommendations)
    - Order status inquiries
    - Restaurant info (hours, location)
    - Restricted to tenant context only (no cross-tenant data)

11. **Create Customer Chat Controller** - Add `POST /api/customer/restaurant/{tenant}/chat` endpoint

12. **Build Customer Chat Widget** - Create [resources/js/components/ai/CustomerChatWidget.jsx](resources/js/components/ai/CustomerChatWidget.jsx):
    - Floating button on customer menu page
    - Suggested questions
    - "Add to cart" actions from recommendations
    - Integrate in [CustomerLayout.jsx](resources/js/layouts/CustomerLayout.jsx)

---

**Phase 5: Smart Recommendations**

13. **Create Recommendation Service** - Build [app/Services/AI/RecommendationService.php](app/Services/AI/RecommendationService.php):
    - Analyze order patterns (frequently bought together)
    - Session-based cart recommendations
    - Personalized suggestions (if customer has history)
    - Time-based suggestions (breakfast vs dinner items)

14. **Add Recommendation Endpoints**:
    - `GET /api/customer/restaurant/{tenant}/recommendations?cart_items=[]`
    - `GET /api/customer/restaurant/{tenant}/popular`

15. **Integrate in Customer Menu** - Enhance [CustomerMenuPage.jsx](resources/js/pages/CustomerMenuPage.jsx) with:
    - "You might also like" section
    - "Popular right now" section
    - Cart-based suggestions

---

**Phase 6: Auto Menu Descriptions**

16. **Create Description Generator Service** - Build [app/Services/AI/MenuDescriptionService.php](app/Services/AI/MenuDescriptionService.php):
    - Generate appetizing descriptions from item name + category
    - Multi-language support (Bengali/English)
    - Regenerate on demand

17. **Add Generate Button** - Modify menu item create/edit form in [MenuItemsPage.jsx](resources/js/pages/MenuItemsPage.jsx):
    - "✨ Generate with AI" button next to description field
    - Preview before saving

---

**Phase 7: Sentiment Analysis (Super Admin)**

18. **Create Sentiment Service** - Build [app/Services/AI/SentimentAnalysisService.php](app/Services/AI/SentimentAnalysisService.php):
    - Analyze contact enquiries sentiment
    - Flag negative feedback for urgent review
    - Summarize tenant health metrics

19. **Enhance Enquiries Page** - Update [AdminEnquiriesPage.jsx](resources/js/pages/admin/AdminEnquiriesPage.jsx) with:
    - Sentiment badges (positive/neutral/negative)
    - "AI Summary" for bulk analysis
    - Priority sorting by sentiment

---

### **Architecture Diagram**

```
┌─────────────────────────────────────────────────────────────┐
│                      Google Gemini API                       │
└─────────────────────────────────────────────────────────────┘
                              ▲
                              │
┌─────────────────────────────────────────────────────────────┐
│                    app/Services/AI/                          │
│  ┌──────────────┐  ┌─────────────┐  ┌──────────────┐        │
│  │GeminiService │  │ RateLimiter │  │ CacheService │        │
│  └──────────────┘  └─────────────┘  └──────────────┘        │
│         ▲                                                    │
│         │                                                    │
│  ┌──────┴───────────────────────────────────────────┐       │
│  │ Analytics │ Forecast │ Chat │ Recommend │ Desc  │        │
│  │ Assistant │ Service  │ Bot  │ Engine    │ Gen   │        │
│  └──────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
                              ▲
                              │
┌─────────────────────────────────────────────────────────────┐
│                   API Controllers                            │
│  AIAnalyticsController │ CustomerChatController │ ...        │
└─────────────────────────────────────────────────────────────┘
                              ▲
                              │
┌─────────────────────────────────────────────────────────────┐
│                   React Frontend                             │
│  AdminAIChat │ CustomerChatWidget │ Recommendations │ ...    │
└─────────────────────────────────────────────────────────────┘
```

---

### **Verification**

1. **Unit Tests:**
   - Test each AI service with mocked Gemini responses
   - Test rate limiting behavior
   - Test tenant isolation in customer chat

2. **Integration Tests:**
   - Test full chat flow (frontend → API → Gemini → response)
   - Test analytics assistant with real queries

3. **Manual Testing:**
   - Ask analytics questions: "What were my sales yesterday?"
   - Test customer chatbot: "What's in the Chicken Biryani?"
   - Verify recommendations appear on cart page
   - Generate menu descriptions and review quality

4. **Rate Limit Verification:**
   - Confirm 1,500 requests/day limit is respected
   - Check fallback messages when quota exceeded

---

### **Decisions**

- **Provider: Google Gemini** - Best free tier (1,500 req/day), easy migration to paid
- **Abstracted AIService** - Provider-agnostic design allows swapping to OpenAI/Claude later
- **Admin-first Implementation** - Analytics Assistant delivers immediate value to restaurant owners
- **Per-tenant Rate Limits** - Fair usage across tenants on shared quota
- **Tenant-isolated Context** - Customer chatbot only sees that restaurant's data
