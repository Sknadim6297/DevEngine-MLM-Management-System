<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</head>


<body>


    @include('admin.partials.sidebar')




    <!-- =========================================================
     MAIN WRAPPER
========================================================= -->

    <div class="main-wrapper">


        @include('admin.partials.topbar')


        <!-- =====================================================
         DASHBOARD CONTENT
    ===================================================== -->

        <main class="dashboard-content">

            <div class="row g-4">

                <!-- =========================
             DASHBOARD
        ========================== -->
                <div class="col-12 col-xl-8">

                    <div class="dashboard-card h-100">

                        <div class="card-body-custom">

                            <div class="row align-items-start g-3">

                                <!-- Dashboard Info -->
                                <div class="col-12 col-md-5">

                                    <h2 class="dashboard-title">
                                        Dashboard
                                    </h2>

                                    <div class="dashboard-subtitle">
                                        Overview of Latest Month
                                    </div>

                                    <div class="earning-amount">
                                        $3468.98
                                    </div>

                                    <div class="earning-label">
                                        Current Month Earnings
                                    </div>

                                    <div class="earning-number">
                                        96
                                    </div>

                                    <div class="earning-label">
                                        Current Month Sales
                                    </div>

                                    <button class="summary-btn">
                                        Last Month Summary
                                    </button>

                                </div>


                                <!-- Earning Chart -->
                                <div class="col-12 col-md-7">

                                    <div class="chart-tabs">

                                        <button type="button" class="active">
                                            Yearly
                                        </button>

                                        <button type="button">
                                            Monthly
                                        </button>

                                        <button type="button">
                                            Weekly
                                        </button>

                                    </div>

                                    <div class="earning-chart-wrapper">

                                        <canvas id="earningChart"></canvas>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- =========================
                     SMALL STATS
                ========================== -->

                        <div class="stat-row">

                            <div class="stat-box">

                                <div class="stat-icon icon-pink">
                                    <i class="bi bi-wallet2"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        Wallet Balance
                                    </span>

                                    <span class="stat-value">
                                        ${{ number_format((float) ($activationWalletTotal ?? 0), 2) }}
                                    </span>

                                </div>

                            </div>


                            <div class="stat-box">

                                <div class="stat-icon icon-purple">
                                    <i class="bi bi-heart-fill"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        Referral Earning
                                    </span>

                                    <span class="stat-value">
                                        $1689.54
                                    </span>

                                </div>

                            </div>


                            <div class="stat-box">

                                <div class="stat-icon icon-blue">
                                    <i class="bi bi-wallet"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        Estimate Sales
                                    </span>

                                    <span class="stat-value">
                                        $2851.54
                                    </span>

                                </div>

                            </div>


                            <div class="stat-box">

                                <div class="stat-icon icon-yellow">
                                    <i class="bi bi-graph-up"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        Earning
                                    </span>

                                    <span class="stat-value">
                                        $52,567.54
                                    </span>

                                </div>

                            </div>


                            <div class="stat-box">

                                <div class="stat-icon icon-blue">
                                    <i class="bi bi-wallet2"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        Working Wallet
                                    </span>

                                    <span class="stat-value">
                                        {{ number_format((float) ($workingWalletTotal ?? 0), 2) }} USDT
                                    </span>

                                </div>

                            </div>


                            <div class="stat-box">

                                <div class="stat-icon icon-yellow">
                                    <i class="bi bi-graph-up"></i>
                                </div>

                                <div class="stat-content">

                                    <span class="stat-label">
                                        ROI Wallet
                                    </span>

                                    <span class="stat-value">
                                        {{ number_format((float) ($roiWalletTotal ?? 0), 2) }} USDT
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =========================
             TRAFFIC
        ========================== -->

                <div class="col-12 col-xl-4">

                    <div class="dashboard-card traffic-card h-100">

                        <div class="card-body-custom">

                            <h3 class="traffic-title">
                                Traffic
                            </h3>

                            <div class="traffic-chart">

                                <canvas id="trafficChart"></canvas>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =========================
             SUMMARY CARDS
        ========================== -->

                <div class="col-12">

                    <div class="row g-4">

                        <!-- Followers -->
                        <div class="col-12 col-sm-6 col-xl-3">

                            <div class="summary-card card-pink h-100">

                                <div class="summary-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>

                                <div class="summary-number">
                                    31.6K
                                </div>

                                <div class="summary-name">
                                    Followers
                                </div>

                            </div>

                        </div>


                        <!-- Page View -->
                        <div class="col-12 col-sm-6 col-xl-3">

                            <div class="summary-card card-purple h-100">

                                <div class="summary-icon">
                                    <i class="bi bi-eye-fill"></i>
                                </div>

                                <div class="summary-number">
                                    $432
                                </div>

                                <div class="summary-name">
                                    Page View
                                </div>

                            </div>

                        </div>


                        <!-- Bounce Rate -->
                        <div class="col-12 col-sm-6 col-xl-3">

                            <div class="summary-card card-blue h-100">

                                <div class="summary-icon">
                                    <i class="bi bi-activity"></i>
                                </div>

                                <div class="summary-number">
                                    $432
                                </div>

                                <div class="summary-name">
                                    Bounce Rate
                                </div>

                            </div>

                        </div>


                        <!-- Revenue -->
                        <div class="col-12 col-sm-6 col-xl-3">

                            <div class="summary-card card-orange h-100">

                                <div class="summary-icon">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>

                                <div class="summary-number">
                                    $432
                                </div>

                                <div class="summary-name">
                                    Revenue Status
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </main>

    </div>


    <!-- =========================================================
     CHART JS
========================================================= -->




    <!-- Bootstrap JS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script>


        const earningCtx =
            document.getElementById('earningChart');

        new Chart(earningCtx, {

            type: 'bar',

            data: {

                labels: [
                    'Jan',
                    'Feb',
                    'Mar',
                    'Apr',
                    'May',
                    'Jun',
                    'Jul',
                    'Aug',
                    'Sep',
                    'Oct',
                    'Nov',
                    'Dec'
                ],

                datasets: [

                    {
                        label: 'Direct',

                        data: [
                            50, 70, 65, 80,
                            60, 75, 55, 70,
                            85, 90, 65, 70
                        ],

                        backgroundColor: '#f3a3cf',

                        borderRadius: 2,

                        barThickness: 6
                    },

                    {
                        label: 'Organic',

                        data: [
                            70, 90, 80, 95,
                            75, 100, 70, 90,
                            110, 95, 80, 85
                        ],

                        backgroundColor: '#c1265c',

                        borderRadius: 2,

                        barThickness: 6
                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        position: 'bottom',

                        labels: {
                            boxWidth: 8,
                            font: {
                                size: 9
                            }
                        }
                    }

                },

                scales: {

                    x: {
                        grid: {
                            display: false
                        },

                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    },

                    y: {

                        beginAtZero: true,

                        grid: {
                            color: '#eeeeee'
                        },

                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    }

                }

            }

        });


        /* =====================================================
           TRAFFIC DONUT
        ===================================================== */

        const trafficCtx =
            document.getElementById('trafficChart');

        new Chart(trafficCtx, {

            type: 'doughnut',

            data: {

                labels: [
                    'Referral',
                    'Google',
                    'Others'
                ],

                datasets: [{

                    data: [
                        50,
                        30,
                        20
                    ],

                    backgroundColor: [
                        '#6524c5',
                        '#d32661',
                        '#e5a42b'
                    ],

                    borderWidth: 2,

                    borderColor: '#ffffff'

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                cutout: '60%',

                plugins: {

                    legend: {

                        position: 'bottom',

                        labels: {

                            boxWidth: 7,

                            font: {
                                size: 9
                            }

                        }

                    }

                }

            }

        });
    </script>
</body>

</html>
