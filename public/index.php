<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/layout.php';

$currentUser = current_user();
if ($currentUser) {
    redirect(default_dashboard_path($currentUser));
}

render_header('Smart AI-Powered ERP for Multi-Location Enterprise');
?>

<!-- ==========================================
     1. HERO SECTION
     ========================================== -->
<section class="hero" id="home">
    <div class="hero-inner">
        <div class="hero-text-col">
            <div class="hero-badge">
                <span>🤖 AI-POWERED PLATFORM</span>
                <span>• Multi-Branch ERP v2.4</span>
            </div>
            <h1 class="hero-title">
                Intelligent Operations for <span class="gradient-text">Beverage Depots</span>
            </h1>
            <p class="hero-desc">
                <strong>360Management ERP</strong> unplugs manual complexity with one unified real-time system across Nigeria. Combine beverage sales, drink distribution, multi-location inventory, payment alert matching, geo-fencing attendance, and predictive AI.
            </p>
            <div class="hero-buttons">
                <a class="btn btn-primary" href="<?= url('login.php') ?>">Get Started Now →</a>
                <a class="btn btn-outline" href="#ai-features">
                    <span>▶</span> Explore AI Assistant
                </a>
            </div>
            <div class="hero-stats-row">
                <div class="hero-stat-item">
                    <strong>99.98%</strong>
                    <span>System Uptime</span>
                </div>
                <div class="hero-stat-item">
                    <strong>3 Branches</strong>
                    <span>Real-time Sync</span>
                </div>
                <div class="hero-stat-item">
                    <strong>98.4%</strong>
                    <span>ERP Health Score</span>
                </div>
            </div>
        </div>

        <div class="hero-mockup-wrap">
            <div class="floating-badge badge-top">
                <span style="font-size:1.5rem">⚡</span>
                <div>
                    <strong style="font-size:0.9rem;display:block">Automated Payment Recon</strong>
                    <small style="color:var(--color-success)">14 Alerts Matched (99% Acc.)</small>
                </div>
            </div>

            <div class="mockup-card">
                <div class="mockup-header">
                    <div class="mockup-dots">
                        <span class="mockup-dot red"></span>
                        <span class="mockup-dot yellow"></span>
                        <span class="mockup-dot green"></span>
                    </div>
                    <span class="mockup-title">360Management Control Dashboard — Victoria Island Branch</span>
                </div>
                <div class="mockup-body">
                    <div class="mockup-kpi-grid">
                        <div class="mock-kpi">
                            <small>Today's Sales</small>
                            <strong>₦18.64M</strong>
                            <span>↑ +2.4% vs yday</span>
                        </div>
                        <div class="mock-kpi">
                            <small>Crate Float</small>
                            <strong>1,480</strong>
                            <span>↑ +5.6%</span>
                        </div>
                        <div class="mock-kpi">
                            <small>Beverage Inventory</small>
                            <strong>8,320 Cases</strong>
                            <span style="color:#10b981">↑ +3.8%</span>
                        </div>
                    </div>

                    <div class="mockup-ai-bar">
                        <div class="ai-icon-pulse">✨</div>
                        <div class="mockup-ai-text">
                            <small>AI Early Warning Alert</small>
                            <p>Coca-Cola 50cl reorder threshold will be reached in 48 hrs at Victoria Island Depot. Recommended stock transfer draft prepared.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="floating-badge badge-bottom">
                <span style="font-size:1.5rem">🛡️</span>
                <div>
                    <strong style="font-size:0.9rem;display:block">Immutable Audit Trail</strong>
                    <small style="color:var(--text-muted)">Maker-Checker Active</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     2. REAL-TIME BUSINESS KPI STRIP
     ========================================== -->
<section class="kpi-strip-section" id="overview">
    <div class="section-container">
        <div class="section-header">
            <h2>Real-time Multi-Location Overview</h2>
            <p>Instant operational metrics across all registered business branches</p>
        </div>

        <div class="kpi-cards-grid">
            <article class="kpi-card">
                <div class="kpi-icon-wrap" style="background:#eef2ff;color:#4f46e5;">₦</div>
                <div class="kpi-info">
                    <small>Today's Total Sales</small>
                    <strong>₦0</strong>
                    <span class="kpi-change">No sales yet</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-icon-wrap" style="background:#ecfdf5;color:#10b981;">▣</div>
                <div class="kpi-info">
                    <small>Crate Float Available</small>
                    <strong>0</strong>
                    <span class="kpi-change">No crate data yet</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-icon-wrap" style="background:#f3e8ff;color:#7c3aed;">🍺</div>
                <div class="kpi-info">
                    <small>Beverage Depot Inventory</small>
                    <strong>0 Cases</strong>
                    <span class="kpi-change">No products yet</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-icon-wrap" style="background:#fffbeb;color:#d97706;">💳</div>
                <div class="kpi-info">
                    <small>Cash &amp; POS Collections</small>
                    <strong>₦0</strong>
                    <span class="kpi-change">No collections yet</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-icon-wrap" style="background:#f5f3ff;color:#7c3aed;">🤖</div>
                <div class="kpi-info">
                    <small>ERP Health Score</small>
                    <strong>Ready</strong>
                    <span class="kpi-change">Awaiting real data</span>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ==========================================
     3. ERP MODULES SHOWCASE
     ========================================== -->
<section class="modules-section" id="modules">
    <div class="section-container">
        <div class="section-header">
            <h2>Comprehensive Functional ERP Modules</h2>
            <p>Every department connected under one modular, role-based platform</p>
        </div>

        <div class="modules-grid">
            <article class="module-card">
                <div class="module-card-icon" style="background:#f3e8ff;color:#7c3aed;">🍺</div>
                <h3>Beverage Depot &amp; Drink Distribution</h3>
                <p>Complete management of soft drinks, alcoholic beverages, energy drinks, bottled water, glass bottle returns, empty crate deposit tracking, wholesale case pricing, and batch/expiry monitoring.</p>
                <ul class="module-card-features">
                    <li>Empty Crate &amp; Bottle Deposit Tracking</li>
                    <li>Wholesale Case &amp; Retail Unit Pricing</li>
                    <li>Batch Expiry &amp; Truck Load Management</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#ecfdf5;color:#10b981;">📦</div>
                <h3>Multi-Location Inventory</h3>
                <p>Track stock across central depots, retail outlets, and warehouses. Batch &amp; expiry management, Goods Received Notes (GRN), and 2-step transfer confirmation.</p>
                <ul class="module-card-features">
                    <li>Inter-Branch Stock Transfer</li>
                    <li>Batch &amp; Expiry Tracking</li>
                    <li>Physical Count Variance</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#fef3c7;color:#d97706;">💳</div>
                <h3>Sales &amp; POS Terminals</h3>
                <p>High-speed cashier POS interface supporting cash, card/POS, bank transfer, wallet, and customer credit. Offline transaction caching with automatic sync.</p>
                <ul class="module-card-features">
                    <li>Multi-Payment Support</li>
                    <li>Offline Transaction Sync</li>
                    <li>Cash Drawer Balancing</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#eef2ff;color:#4f46e5;">₦</div>
                <h3>Payment Alert Reconciliation</h3>
                <p>Automated IMAP crawler that reads bank credit alert emails, extracts transaction reference, date, amount, and matches against open invoices with confidence scoring.</p>
                <ul class="module-card-features">
                    <li>Bank Alert Email Parser</li>
                    <li>CSV / PDF Statement Matching</li>
                    <li>Paystack &amp; Stripe Webhooks</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#f5f3ff;color:#7c3aed;">👥</div>
                <h3>HR &amp; Geo-Fencing Attendance</h3>
                <p>Employee profiles, salary structure, statutory deductions, payslips, and mobile GPS clock-in/out bounded by branch radius with selfie verification.</p>
                <ul class="module-card-features">
                    <li>GPS Clock-in with Radius</li>
                    <li>Selfie Photo Verification</li>
                    <li>Automated Payroll Sync</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#fae8ff;color:#c026d3;">📋</div>
                <h3>Procurement &amp; Vendors</h3>
                <p>Vendor due diligence onboarding, purchase requisitions, RFQs, vendor quotation comparison table, Purchase Orders (PO), and 3-way matching.</p>
                <ul class="module-card-features">
                    <li>Vendor Due Diligence</li>
                    <li>Quotation Comparison Table</li>
                    <li>3-Way Matching (PO/GRN/Invoice)</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#f1f5f9;color:#475569;">⚙️</div>
                <h3>Asset &amp; Maintenance</h3>
                <p>Register operational assets (pumps, tanks, generators, vehicles, filling machines). Preventive maintenance schedules, fault reports, and equipment health scoring.</p>
                <ul class="module-card-features">
                    <li>Asset Health Scoring (0-100%)</li>
                    <li>Work Order Management</li>
                    <li>Downtime Tracking</li>
                </ul>
            </article>

            <article class="module-card">
                <div class="module-card-icon" style="background:#ecfeff;color:#0891b2;">🚚</div>
                <h3>Logistics &amp; Fleet</h3>
                <p>Dispatch scheduling, driver vehicle allocation, route planning, Proof of Delivery (POD) image capture, vehicle operating cost tracking, and deviation alerts.</p>
                <ul class="module-card-features">
                    <li>Route Planning &amp; GPS</li>
                    <li>Proof of Delivery (POD)</li>
                    <li>Vehicle Mileage Tracking</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- ==========================================
     4. AI INTELLIGENCE SPOTLIGHT
     ========================================== -->
<section class="ai-section" id="ai-features">
    <div class="ai-inner">
        <div class="ai-content">
            <div style="display:inline-block;padding:0.3rem 0.8rem;background:rgba(6,182,212,0.2);color:var(--color-accent);border-radius:50px;font-weight:700;font-size:0.8rem;margin-bottom:1rem;">
                CENTRAL INTELLIGENCE LAYER
            </div>
            <h2>AI Assistant Embedded in Every Workflow</h2>
            <p>
                360Management ERP features a provider-independent AI engine that operates as a secure operational assistant across all business modules.
            </p>

            <div class="ai-features-list">
                <div class="ai-feature-item">
                    <div class="ai-feature-icon">💬</div>
                    <div>
                        <strong>Natural Language Prompt Centre</strong>
                        <span>Ask questions like <em>"Show today's sales across all branches"</em> or <em>"Draft purchase request for 100 filters"</em>.</span>
                    </div>
                </div>

                <div class="ai-feature-item">
                    <div class="ai-feature-icon">🧠</div>
                    <div>
                        <strong>RAG Knowledge Base &amp; SOP Support</strong>
                        <span>Internal policies, pump operating procedures, safety guidelines, and employee handbooks indexed for instant guidance.</span>
                    </div>
                </div>

                <div class="ai-feature-item">
                    <div class="ai-feature-icon">🛡️</div>
                    <div>
                        <strong>Strict Human Approval Controls</strong>
                        <span>High-risk actions (payments, journal posting, payroll, price updates, stock changes) <strong>ALWAYS</strong> require human sign-off.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="prompt-box">
            <div class="prompt-box-header">
                <div class="prompt-box-title">
                    <span>✨ Interactive AI Prompt Simulator</span>
                </div>
                <small style="color:var(--text-light)">Click a prompt chip to simulate:</small>
            </div>

            <div class="prompt-chips">
                <span class="prompt-chip active" data-prompt="sales">Today's Sales</span>
                <span class="prompt-chip" data-prompt="inventory">Stock Reorder</span>
                <span class="prompt-chip" data-prompt="payments">Payment Alerts</span>
                <span class="prompt-chip" data-prompt="health">ERP Health</span>
            </div>

            <div class="prompt-result" id="promptResultBox">
                <div class="prompt-result-head">
                    <span>● AI Sales Intelligence Summary (Today)</span>
                    <span>No real records yet</span>
                </div>
                <p>
                    No real sales have been recorded yet. Start selling from the Beverage POS to build today's report.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     5. SYSTEM HEALTH SCORE SECTION
     ========================================== -->
<section class="health-section" id="health-score">
    <div class="section-container">
        <div class="section-header">
            <h2>Overall ERP Health Score Engine</h2>
            <p>Continuous evaluation of Technical, Data, Operational, Financial, and Security posture</p>
        </div>

        <div class="health-grid">
            <div class="health-card">
                <div class="health-card-head">
                    <strong>Technical Health</strong>
                    <span class="health-score-pill healthy">99.8%</span>
                </div>
                <div class="health-card-body">
                    <strong>Healthy</strong>
                    <p>Zero database latencies, background queue jobs active, server load optimal.</p>
                </div>
            </div>

            <div class="health-card">
                <div class="health-card-head">
                    <strong>Data Quality Health</strong>
                    <span class="health-score-pill healthy">97.5%</span>
                </div>
                <div class="health-card-body">
                    <strong>Healthy</strong>
                    <p>No negative stock balances detected. Masters up to date.</p>
                </div>
            </div>

            <div class="health-card">
                <div class="health-card-head">
                    <strong>Financial Health</strong>
                    <span class="health-score-pill healthy">99.2%</span>
                </div>
                <div class="health-card-body">
                    <strong>Healthy</strong>
                    <p>Cash flow positive. Payment alert matching accuracy high.</p>
                </div>
            </div>

            <div class="health-card">
                <div class="health-card-head">
                    <strong>Security &amp; Audit</strong>
                    <span class="health-score-pill healthy">97.0%</span>
                </div>
                <div class="health-card-body">
                    <strong>Healthy</strong>
                    <p>Role permissions strictly enforced. Zero suspicious clock-ins.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php render_footer(); ?>
