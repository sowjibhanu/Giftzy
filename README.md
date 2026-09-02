# GiftZy Business Manager

A small PHP + MySQL application that consolidates the GiftZy Investment workbook
(sales, expenses, investments and monthly collections) into one editable
place. Plain PHP with PDO — no frameworks, no build
step, so it runs on GoDaddy Linux shared hosting as-is.

## What is inside

| Page | What it does |
| --- | --- |
| Dashboard | Totals for sales, profit, expenses by money source, account and cash balances, investment, pending customer money, plus a month-by-month chart and top items/categories |
| Sales | Every item-level sale entry; filter by month, payment type, item or customer; add / edit / delete. Total and profit are calculated from qty, selling price and cost price |
| Expenses | Every expense; filter by month, money source, category or text; add / edit / delete |
| Investments | Total investment = every expense paid with Sowji's or Lavanya's own money, per partner, with the sheet's summary entries kept below as reference |
| Monthly | One row per month: sales and profit from the sale entries, editable online / cash collections, and expenses split by money source. Every month up to the current one appears on its own — no sheet to create when a new month starts |
| Balances | Live sale-account and cash-box balances with a month-by-month running balance, and editable opening balances to tally against the bank passbook and the cash box |

"Paid from" (money source) is the self vs. account distinction from the sheets:

* `Sowji` / `Lavanya` — partner's own money (self funded)
* `Account` — the business sale account
* `Cash` — the shop cash box
* `Shop` — the "Giftzy" column in the workbook
* `Other`

## Imported data

`install/seed.sql` was generated from `GiftZy Investment.xlsx` by
`install/make_seed.py`. Row counts and reconciliation against the workbook's own
totals:

| Table | Rows | Check |
| --- | --- | --- |
| sales | 1,119 | total ₹12,56,860 = "Total Sale Till Date" in the Amounts sheet |
| expenses | 546 | Account ₹11,57,528.04 = "Expenditure From Sale Account" |
| investments | 6 | ₹12,30,608 = Sowji + Lavanya own-money expenses |
| monthly_collections | 14 | Aug 2025 – Sep 2026 online/cash figures |

Rows where two partners paid for the same purpose became two expense rows (one
per partner) so that per-person totals stay correct. Free-form side notes in the
far columns of the workbook (per-person shopping lists, combo price tables) were
imported where they were tabular; anything that was a running note was left out.

To regenerate the seed file after changing the workbook:

```bash
pip install openpyxl
python3 install/make_seed.py "GiftZy Investment.xlsx" install/seed.sql
```

## Balances

* Account balance = opening + sales not paid in cash + account adjustments −
  expenses paid from `Account`
* Cash balance = opening + sales paid in cash + cash adjustments − expenses paid
  from `Cash`

Every sale is money in by itself: `Cash` sales go into the cash box, the rest
into the sale account, and `pending_amount` is left out until it is collected.
Partner (`Sowji` / `Lavanya`) spending is their own money, so it shows on the
dashboard and Investments but moves neither balance. The two editable columns on
Monthly are adjustments — anything else that moved money that month (top-ups,
transfers between account and cash box, differences against the passbook).

Everything is computed on every page load, so adding or editing a sale, an
expense or an adjustment moves the balances immediately. A new month gets its own
row automatically on Monthly and Balances (highlighted as "this month"), carrying
the previous month's closing balance forward. Expenses with no date sit in the
"Opening / no date" row; sales with no date are left out of the balances until
they are given a date.

The seed sets the openings so the balances land exactly on the workbook's own
figures (account ₹38,833.69, cash ₹10,720), and the imported adjustments are the
difference between the sheet's monthly collected figures and the sale entries of
that month. The cash opening is negative (−₹17,365) because the workbook's cash
box was topped up from, and moved into, the account without those transfers being
recorded as rows. Change both values
on the Balances page whenever the app should line up with the current passbook
and cash box.

## Installing on GoDaddy shared hosting

1. **Create the database.** cPanel → *MySQL Databases*: create a database and a
   user, and add the user to the database with all privileges. Note the names —
   GoDaddy prefixes them, e.g. `myacct_giftzy`.
2. **Import the data.** cPanel → *phpMyAdmin* → select the database → *Import* →
   upload `install/schema.sql`, then `install/seed.sql`. (If the upload limit
   rejects `seed.sql`, zip it first — phpMyAdmin accepts `.sql.zip`.)
3. **Set the password.** Open `install/hash.php?p=your+new+password` in the
   browser (or run `php install/hash.php "your new password"`), copy the hash
   into `password_hash` in `config.php`, and delete `install/hash.php` from the
   server afterwards.
4. **Create `config.php`.** Copy `config.sample.php` to `config.php` (that file is
   git-ignored so credentials stay out of the repository) and fill in the database
   name, user and password from step 1 plus the password hash from step 3.
5. **Upload** everything (File Manager or FTP) into `public_html/giftzy`, keeping
   the folder structure, then open `https://yourdomain.com/giftzy/`.

`.htaccess` files block direct browsing of `config.php` and of the `lib`,
`partials` and `install` folders. PHP 7.4 or newer works; the PDO MySQL
extension must be enabled (cPanel → *Select PHP Version*).

Delete `install/` and `check.php` from the server once the app runs.

## If a page returns HTTP 500

Open `check.php` in the browser: it prints the PHP version, whether `pdo_mysql`
is enabled, whether `config.php` is present and filled in, and whether every
table exists. The usual causes are a missing `config.php`, database credentials
that do not match the ones cPanel created, or `AllowOverride` rejecting an
`.htaccess` directive — in that last case the Apache error log names the line,
and the `.htaccess` files can simply be deleted (they only hide files from
direct browsing).

## Local run

```bash
mysql -e "CREATE DATABASE giftzy CHARACTER SET utf8mb4"
mysql giftzy < install/schema.sql && mysql giftzy < install/seed.sql
php -S 127.0.0.1:8080
```
