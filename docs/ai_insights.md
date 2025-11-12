# AI-Powered Energy Insights

## Overview

The AI Insights feature provides intelligent, actionable recommendations for energy optimization using advanced AI models from leading providers (OpenAI, Anthropic, Google AI, Azure OpenAI). This feature analyzes consumption patterns, costs, anomalies, and carbon emissions to help optimize energy usage across your estate.

## Features

### 📊 Consumption Pattern Analysis
- Identify daily, weekly, and seasonal trends
- Analyze baseload consumption
- Detect peak usage times
- Understand usage patterns across different meter types

### 💰 Cost Optimization
- Find savings opportunities
- Recommend optimal time-of-use strategies
- Suggest tariff switching opportunities
- Identify wasteful consumption patterns

### ⚠️ Anomaly Detection
- Detect unexpected spikes or drops
- Identify missing data periods
- Flag potential equipment issues
- Monitor data quality

### 🌱 Carbon Reduction Strategies
- Suggest peak demand reduction opportunities
- Recommend load shifting strategies
- Identify energy efficiency measures
- Calculate carbon impact of changes

### 🔧 Predictive Maintenance
- Anticipate equipment problems
- Identify degrading performance
- Recommend preventive actions
- Monitor equipment health trends

## Supported AI Providers

### OpenAI (GPT-4)
- **Model:** gpt-4o-mini (default) or gpt-4
- **Strengths:** Excellent general-purpose analysis, strong reasoning
- **Cost:** ~$0.03 per insight (using gpt-4o-mini)
- **API Key:** [Get at OpenAI Platform](https://platform.openai.com/api-keys)

### Anthropic (Claude)
- **Model:** claude-3-5-sonnet-20241022 (default)
- **Strengths:** Detailed technical analysis, safety-focused recommendations
- **Cost:** ~$0.015 per insight
- **API Key:** [Get at Anthropic Console](https://console.anthropic.com/)

### Google AI (Gemini)
- **Model:** gemini-pro (default)
- **Strengths:** Fast insights, cost-effective
- **Cost:** Free tier available, then ~$0.001 per insight
- **API Key:** [Get at Google AI Studio](https://makersuite.google.com/app/apikey)

### Azure OpenAI
- **Model:** gpt-4 (configurable)
- **Strengths:** Enterprise integration, compliance features
- **Cost:** Varies by Azure subscription
- **API Key:** [Configure in Azure Portal](https://portal.azure.com/)

## Setup & Configuration

### Step 1: Choose an AI Provider

Select one of the supported providers based on your needs:
- **Enterprise/Compliance:** Azure OpenAI
- **Best Quality:** OpenAI GPT-4 or Anthropic Claude
- **Cost-Effective:** Google Gemini or OpenAI gpt-4o-mini

### Step 2: Obtain API Key

Visit your chosen provider's website and create an account:
1. Sign up for the service
2. Navigate to API keys section
3. Generate a new API key
4. Copy the key securely

### Step 3: Configure Environment Variables

Add your API key to the `.env` file in the project root:

```bash
# For OpenAI (recommended for most users)
OPENAI_API_KEY=sk-proj-your-api-key-here
OPENAI_MODEL=gpt-4o-mini

# OR for Anthropic
ANTHROPIC_API_KEY=sk-ant-your-api-key-here
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022

# OR for Google AI
GOOGLE_AI_API_KEY=your-api-key-here
GOOGLE_MODEL=gemini-pro

# OR for Azure OpenAI
AZURE_OPENAI_API_KEY=your-api-key-here
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_MODEL=gpt-4
```

**Note:** You only need to configure ONE provider. The system will automatically use the first configured provider it finds.

### Step 4: Restart Web Server

Restart your web server to load the new environment variables:

```bash
# On most Linux systems
sudo systemctl restart php-fpm

# Or for Apache
sudo service apache2 restart

# On Plesk
# Go to: Domains → Your Domain → PHP Settings → Reload/Restart PHP
```

### Step 5: Verify Configuration

1. Navigate to `/admin/ai-insights/settings`
2. Check that your chosen provider shows as "Configured"
3. Return to `/admin/ai-insights` to start generating insights

## Usage

### Generating Insights via Web UI

1. Navigate to **Admin → AI Insights** (`/admin/ai-insights`)
2. Select a meter from the dropdown
3. Choose an insight type (or leave blank for comprehensive analysis)
4. Click **Generate Insight**
5. Wait 10-30 seconds for the AI to analyze and generate recommendations

### Generating Insights via CLI (Future Enhancement)

```bash
# Generate insights for a specific meter
php scripts/generate_ai_insights.php --meter-id 123

# Generate insights for all meters
php scripts/generate_ai_insights.php --all

# Generate specific type of insights
php scripts/generate_ai_insights.php --meter-id 123 --type cost_optimization
```

### Viewing and Managing Insights

- **Dashboard View:** See recent insights across all meters at `/admin/ai-insights`
- **Meter View:** See all insights for a specific meter at `/admin/ai-insights/meter/{id}`
- **Dismiss:** Click "Dismiss" to hide insights you've addressed
- **Priority Levels:** Insights are tagged as High, Medium, or Low priority

## Data Privacy & Security

### What Data is Sent to AI Providers

The system sends **aggregated statistics only**, never raw meter readings:
- Total consumption over analysis period
- Average daily consumption
- Maximum and minimum daily values
- Meter metadata (MPAN, site name, energy type)
- Tariff information (if applicable)

**NOT sent:**
- Individual half-hourly readings
- Personal information
- Billing data
- Complete reading history

### Security Best Practices

1. **API Key Security:**
   - Store API keys in `.env` file only
   - Never commit `.env` to version control
   - Use `.gitignore` to exclude `.env`
   - Rotate keys periodically

2. **Cost Control:**
   - Monitor API usage through provider dashboards
   - Set up billing alerts
   - Review generated insights regularly
   - Consider rate limiting for automated generation

3. **GDPR Compliance:**
   - Review provider's data processing terms
   - Ensure compliance with your data protection requirements
   - Document AI provider usage in privacy policy
   - Obtain necessary consents if required

## Database Schema

The `ai_insights` table stores generated insights:

```sql
CREATE TABLE ai_insights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    insight_date DATE NOT NULL,
    insight_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    recommendations JSON NULL,
    confidence_score DECIMAL(5, 2) NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_dismissed BOOLEAN DEFAULT FALSE,
    dismissed_by INT UNSIGNED NULL,
    dismissed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

## API Endpoints

### Generate Insight (POST)
```
POST /admin/ai-insights/generate
Body: {
    "meter_id": 123,
    "insight_type": "consumption_pattern" (optional)
}
```

### Dismiss Insight (POST)
```
POST /admin/ai-insights/{id}/dismiss
```

### View Insights (GET)
```
GET /admin/ai-insights
GET /admin/ai-insights/meter/{id}
GET /admin/ai-insights/settings
```

## Troubleshooting

### AI Provider Not Configured Error

**Problem:** "No AI provider configured" message appears

**Solutions:**
1. Verify `.env` file has the correct API key
2. Check key format matches provider requirements
3. Restart web server to load environment changes
4. Check file permissions on `.env` (should be 644)

### API Call Failed Error

**Problem:** "API call failed" or HTTP error codes

**Solutions:**
1. Verify API key is valid and active
2. Check internet connectivity from server
3. Ensure API endpoint URLs are correct
4. Verify account has sufficient credits/quota
5. Check provider's status page for outages

### Failed to Parse AI Response

**Problem:** AI response cannot be parsed as JSON

**Solutions:**
1. This is usually temporary - try generating again
2. Check if provider's API response format changed
3. Review error logs for specific parsing errors
4. May need to update service to handle new format

### High Costs

**Problem:** API costs are higher than expected

**Solutions:**
1. Use gpt-4o-mini instead of gpt-4 for OpenAI
2. Switch to Google Gemini (most cost-effective)
3. Limit automated insight generation
4. Review and dismiss old insights regularly
5. Set up billing alerts with provider

## Best Practices

1. **Generate Insights Strategically:**
   - Focus on high-consumption meters first
   - Generate insights weekly/monthly, not daily
   - Address recommendations before generating new insights

2. **Review and Act:**
   - Review insights regularly in team meetings
   - Track implementation of recommendations
   - Measure impact of changes
   - Dismiss addressed insights

3. **Cost Management:**
   - Start with free/low-cost providers (Google Gemini)
   - Only generate when needed, not automatically
   - Use gpt-4o-mini rather than full gpt-4
   - Monitor monthly API costs

4. **Data Quality:**
   - Ensure meters have sufficient history (30+ days recommended)
   - Address data quality issues before generating insights
   - Run data quality checks regularly

## Future Enhancements

Planned improvements to AI Insights:

- [ ] Automated scheduled insight generation
- [ ] Bulk insight generation for multiple meters
- [ ] Insight comparison across similar meters
- [ ] Export insights to PDF/email reports
- [ ] Integration with alarms and notifications
- [ ] Historical insight tracking and trends
- [ ] Custom prompt templates
- [ ] Multi-language support
- [ ] Insight effectiveness tracking

## Support

For issues or questions:
1. Check this documentation first
2. Review provider's API documentation
3. Check error logs in `logs/app.log`
4. Verify configuration in `/admin/ai-insights/settings`

## Related Documentation

- [Analytics Features](analytics_features.md)
- [Carbon Intensity Implementation](carbon_intensity_implementation.md)
- [Tariff Switching Analysis](tariff_switching_analysis.md)
- [System Settings Guide](system_settings_guide.md)
