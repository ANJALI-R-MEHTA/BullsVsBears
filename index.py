import yfinance as yf
import mysql.connector
from datetime import datetime, timedelta

#MySQL connection
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",  
    database="nifty50"
)

cursor = db.cursor()

#function to create the table if it doesn't exist
def create_table():
    cursor.execute('''CREATE TABLE IF NOT EXISTS nifty_50_closing_prices (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(50) NULL,
        stock_symbol VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (stock_symbol)
    );''')
    db.commit()

#dynamically adjust columns for the last 30 trading days
def update_table_for_last_30_days():
    #last 30 trading days using Yahoo Finance
    today = datetime.now()
    past_date = today - timedelta(days=30)
    data = yf.download("^NSEI", start=past_date.strftime('%Y-%m-%d'), end=today.strftime('%Y-%m-%d'))

    #filter for working days only
    trading_days = sorted(data.index.strftime('%Y-%m-%d').tolist())
    
    #current columns in the table
    cursor.execute("SHOW COLUMNS FROM nifty_50_closing_prices")
    existing_columns = [column[0] for column in cursor.fetchall()]

    #new columns if they don't exist
    for date in trading_days:
        if date not in existing_columns:
            cursor.execute(f"ALTER TABLE nifty_50_closing_prices ADD COLUMN `{date}` FLOAT")

    #remove old date columns if necessary
    for col in existing_columns:
        if col.startswith('20') and col not in trading_days:
            cursor.execute(f"ALTER TABLE nifty_50_closing_prices DROP COLUMN `{col}`")

    db.commit()
    return trading_days

#fetch and insert data into the table
def fetch_and_insert_data():
    create_table()

    #updating the table schema for the last 30 days
    trading_days = update_table_for_last_30_days()

    #n50
    stock_company_map = {
        'TCS.NS': 'Tata Consultancy Services',
        'INFY.NS': 'Infosys',
        'RELIANCE.NS': 'Reliance Industries',
        'HDFCBANK.NS': 'HDFC Bank',
        'ITC.NS': 'ITC Limited',
        'KOTAKBANK.NS': 'Kotak Mahindra Bank',
        'ICICIBANK.NS': 'ICICI Bank',
        'SBIN.NS': 'State Bank of India',
        'LT.NS': 'Larsen & Toubro',
        'TATAMOTORS.NS': 'Tata Motors',
        'AXISBANK.NS': 'Axis Bank',
        'BHARTIARTL.NS': 'Bharti Airtel',
        'HINDUNILVR.NS': 'Hindustan Unilever',
        'BAJFINANCE.NS': 'Bajaj Finance',
        'ADANIPORTS.NS': 'Adani Ports',
        'TITAN.NS': 'Titan Company',
        'ULTRACEMCO.NS': 'Ultratech Cement',
        'MARUTI.NS': 'Maruti Suzuki',
        'GRASIM.NS': 'Grasim Industries',
        'SUNPHARMA.NS': 'Sun Pharmaceutical',
        'POWERGRID.NS': 'Power Grid Corporation',
        'NTPC.NS': 'NTPC Limited',
        'WIPRO.NS': 'Wipro Limited',
        'ONGC.NS': 'Oil and Natural Gas Corporation',
        'JSWSTEEL.NS': 'JSW Steel',
        'TATASTEEL.NS': 'Tata Steel',
        'BPCL.NS': 'Bharat Petroleum',
        'HCLTECH.NS': 'HCL Technologies',
        'BAJAJFINSV.NS': 'Bajaj Finserv',
        'INDUSINDBK.NS': 'IndusInd Bank',
        'ASIANPAINT.NS': 'Asian Paints',
        'BRITANNIA.NS': 'Britannia Industries',
        'HEROMOTOCO.NS': 'Hero MotoCorp',
        'EICHERMOT.NS': 'Eicher Motors',
        'HDFCLIFE.NS': 'HDFC Life',
        'M&M.NS': 'Mahindra & Mahindra',
        'DRREDDY.NS': 'Dr. Reddy’s Laboratories',
        'CIPLA.NS': 'Cipla Limited',
        'HINDALCO.NS': 'Hindalco Industries',
        'BEL.NS': 'Bharat Electronics',
        'COALINDIA.NS': 'Coal India',
        'TECHM.NS': 'Tech Mahindra',
        'APOLLOHOSP.NS': 'Apollo Hospitals',
        'SBILIFE.NS': 'SBI Life Insurance',
        'TATACONSUM.NS': 'Tata Consumer Products',
        'TRENT.NS': 'Trent Limited',
        'BAJAJ-AUTO.NS': 'Bajaj Auto',
        'NESTLEIND.NS': 'Nestle India',
        'SHRIRAMFIN.NS': 'Shriram Finance',
        'ADANIENT.NS': 'Adani Enterprises'
    }

    for stock, company_name in stock_company_map.items():
        try:
            #data for the last month
            data = yf.Ticker(stock).history(period="1mo")
            print(f"Fetching data for {company_name} ({stock})")
            
            #get the last 30 closing prices 
            closing_prices = [data.loc[date]['Close'] if date in data.index else None for date in trading_days]

            #dynamic SQL for inserting data with date columns
            columns = ', '.join([f"`{date}`" for date in trading_days])
            placeholders = ', '.join(['%s'] * len(trading_days))
            
            sql = f"""
            REPLACE INTO nifty_50_closing_prices 
            (name, stock_symbol, {columns})
            VALUES (%s, %s, {placeholders})
            """
            val = (company_name, stock, *closing_prices)
            cursor.execute(sql, val)
            db.commit()

        except Exception as e:
            print(f"Failed to fetch data for {company_name} ({stock}): {e}")

#function to fetch and insert data
fetch_and_insert_data()

#close the database connection
cursor.close()
db.close()
