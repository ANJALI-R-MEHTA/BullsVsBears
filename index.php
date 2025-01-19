<?php
// Database connection
$host = 'localhost';
$dbname = 'nifty50';
$user = 'root';
$pass = '';

//establish MySQL connection
$mysqli = new mysqli($host, $user, $pass, $dbname);

//check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$search = '';
$stock_data = [];

//variables for chart data
$dates = [];
$closing_prices = [];
$trend = '';

//check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search = $_POST['search'];

    //the SQL query to fetch data by stock_symbol or company name
    $stmt = $mysqli->prepare("
        SELECT * FROM nifty_50_closing_prices 
        WHERE stock_symbol = ? OR name = ?
    ");
    $stmt->bind_param('ss', $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();

    //fetch the result and store it in an array
    if ($result->num_rows > 0) {
        $stock_data = $result->fetch_assoc();
        foreach ($stock_data as $key => $value) {
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $key)) {
                $dates[] = $key;
                $closing_prices[] = $value !== null ? (float)$value : 0;
            }
        }

        //sorting dates and closing prices in ascending order
        array_multisort($dates, SORT_ASC, $closing_prices);

        //determine the trend
        if (count($closing_prices) > 1) {
            $trend = end($closing_prices) > reset($closing_prices) ? 'Bullish' : 'Bearish';
        }
    } else {
        echo "<p class='message'>No results found for '$search'</p>";
    }

    $stmt->close();
}

//close the connection
$mysqli->close();

//determine the color based on the trend
$chartColor = ($trend === "Bullish") ? "green" : "red";
$chartBackgroundColor = ($trend === "Bullish") ? "rgba(0, 255, 0, 0.2)" : "rgba(255, 0, 0, 0.2)";
$chartBorderColor = ($trend === "Bullish") ? "green" : "red";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nifty 50 Stock Data</title>
    <style>
        /*styling */
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to right, #0a0a0a, #121212);
            color: #ffffff;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            color: #F5F5DC;
            margin-top: 20px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .container {
            display: flex;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background-color: #1e1e1e;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        /* Left section styling */
        .left-section {
            width: 50%;
            padding: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        input[type="text"] {
            padding: 12px;
            border: 2px solid #003366;
            border-radius: 4px;
            font-size: 1.1em;
            background-color: #2a2a2a;
            color: #ffffff;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        button {
            background-color: #003366;
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .trend {
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px;
            border-radius: 8px;
        }

        .bullish { background-color: #32CD32; }
        .bearish { background-color: #FF4500; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ffffff;
        }

        th, td {
            padding: 8px;
            text-align: center;
            background-color: #2a2a2a;
        }

        th {
            background-color: #003366;
        }

        /* Right section styling */
        .right-section {
            width: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Chart styling */
        #myChart {
            width: 100%;
            height: 400px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: #1e1e1e;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        /* Image grid styling */
        .image-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
        }

        .image-container {
            position: relative;
            width: 100%;
            height: 150px;
            overflow: hidden;
            border-radius: 8px;
            background-color: #2a2a2a;
            cursor: pointer;
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .image-container:hover img {
            transform: scale(1.1);
        }

        .image-hover-text {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 5px;
            text-align: center;
            background: rgba(0, 0, 0, 0.7);
            color: #ffffff;
            font-weight: bold;
            font-size: 1em;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .image-container:hover .image-hover-text {
            opacity: 1;
        }
    </style>
</head>
<body>
    <h1>Nifty 50 Closing Price Analysis</h1>
    <div class="container">
        <div class="left-section">
            <form method="post">
                <input type="text" name="search" placeholder="Enter stock symbol or company name" value="<?php echo htmlspecialchars($search); ?>" required>
                <button type="submit">Search</button>
            </form>

            <?php if (!empty($closing_prices)) : ?>
                <div class="trend <?php echo ($trend === "Bullish") ? 'bullish' : 'bearish'; ?>">
                    <strong><?php echo $trend; ?></strong>
                </div>
            <?php endif; ?>

            <table>
                <tr><th>Date</th><th>Closing Price</th></tr>
                <?php foreach ($dates as $index => $date) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($date); ?></td>
                        <td><?php echo htmlspecialchars($closing_prices[$index]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="right-section">
            <canvas id="myChart"></canvas>

            <div class="image-grid">
                <div class="image-container">
                    <img src="images\bull.jpg" alt="Bearish">
                    <div class="image-hover-text">Bearish is used to describe the belief that prices will go down.Deriving from the downward paw motion abear strikes wit</div>
                </div>
                <div class="image-container">
                    <img src="images\bear.jpg" alt="Bullish">
                    <div class="image-hover-text">Bullish refers to the optimistic belief that prices will go up. The name derives from a bull striking their horns upwards.</div>
                </div>
                <div class="image-container">
                    <img src="images\n50.jpg" alt="nifty 50">
                    <div class="image-hover-text">NIFTY 50 is a benchmark based index and also the flagship of NSE, which showcases the top 50 equity stocks traded in the stock exchange out of a total of  2266 stocks..</div>
                </div>
                <div class="image-container">
                    <img src="images\cp.jpg" alt="closing prices">
                    <div class="image-hover-text">Closing price is the last price at which a stock trades during a regular trading session.</div>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const dates = <?php echo json_encode($dates); ?>;
        const closingPrices = <?php echo json_encode($closing_prices); ?>;
        const trendColor = "<?php echo $chartColor; ?>";
        const chartBackgroundColor = "<?php echo $chartBackgroundColor; ?>";
        const chartBorderColor = "<?php echo $chartBorderColor; ?>";

        const ctx = document.getElementById('myChart').getContext('2d');
        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Closing Price',
                    data: closingPrices,
                    backgroundColor: chartBackgroundColor,
                    borderColor: chartBorderColor,
                    borderWidth: 1,
                    fill: true
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'category',
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Closing Price'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
