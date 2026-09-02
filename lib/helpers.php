<?php

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function money($v)
{
    global $CONFIG;
    return $CONFIG['currency'] . number_format((float) $v, 2);
}

/** Numbers arrive from forms as text; empty means 0, not NULL. */
function post_num($key, $default = 0)
{
    $v = $_POST[$key] ?? '';
    return $v === '' ? $default : (float) str_replace(',', '', $v);
}

function post_str($key, $nullable = true)
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $v === '' && $nullable ? null : $v;
}

function post_date($key)
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function q($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function redirect($url, $flash = null)
{
    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
    header('Location: ' . $url);
    exit;
}

function flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert success">' . h($msg) . '</div>';
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Session expired, please reload the page and try again.');
    }
}

function scalar(PDO $db, $sql, $params = [])
{
    $st = $db->prepare($sql);
    $st->execute($params);
    return (float) $st->fetchColumn();
}

function rows(PDO $db, $sql, $params = [])
{
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function one(PDO $db, $sql, $params = [])
{
    $st = $db->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Opening balances, keyed by name, with 0 for anything not stored yet. */
function settings(PDO $db)
{
    $values = ['opening_account' => 0.0, 'opening_cash' => 0.0];
    foreach (rows($db, 'SELECT name, value FROM settings') as $r) {
        $values[$r['name']] = (float) $r['value'];
    }
    return $values;
}

function save_setting(PDO $db, $name, $value)
{
    $st = $db->prepare('INSERT INTO settings (name, value) VALUES (?,?)
                        ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $st->execute([$name, $value]);
}

/** Builds "WHERE ..." from filters that are only applied when non-empty. */
function build_where(array $clauses)
{
    $sql = [];
    $params = [];
    foreach ($clauses as $clause) {
        [$condition, $value] = $clause;
        if ($value === '' || $value === null) {
            continue;
        }
        $sql[] = $condition;
        $params = array_merge($params, is_array($value) ? $value : [$value]);
    }
    return [$sql ? ' WHERE ' . implode(' AND ', $sql) : '', $params];
}

function month_options(PDO $db, $table, $column)
{
    $sql = "SELECT DISTINCT DATE_FORMAT($column,'%Y-%m') p FROM $table
            WHERE $column IS NOT NULL ORDER BY p DESC";
    return array_column($db->query($sql)->fetchAll(), 'p');
}

/**
 * Every month from the earliest given period up to the current one, so a new
 * month shows up on its own without anyone having to create it.
 */
function month_series(array $periods)
{
    $periods = array_filter($periods);
    $start = $periods ? min($periods) : date('Y-m');
    $out = [];
    for ($m = strtotime($start . '-01'), $end = strtotime(date('Y-m') . '-01'); $m <= $end;
         $m = strtotime('+1 month', $m)) {
        $out[] = date('Y-m', $m);
    }
    return array_values(array_unique(array_merge($out, $periods)));
}

function month_label($period)
{
    return $period ? date('M Y', strtotime($period . '-01')) : '(no date)';
}

const FUND_SOURCES = ['Sowji', 'Lavanya', 'Account', 'Cash', 'Shop', 'Other'];
const PAYMENT_TYPES = ['Online', 'Cash', 'Pending', 'Other'];

/** Coloured pill for a payment type or a fund source. */
function pill($value)
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    return '<span class="pill pill-' . h($value) . '">' . h($value) . '</span>';
}

function select_field($name, $options, $selected, $blank = null)
{
    $out = '<select class="select" name="' . h($name) . '" id="' . h($name) . '">';
    if ($blank !== null) {
        $out .= '<option value="">' . h($blank) . '</option>';
    }
    foreach ($options as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        $sel = ((string) $value === (string) $selected) ? ' selected' : '';
        $out .= '<option value="' . h($value) . '"' . $sel . '>' . h($label) . '</option>';
    }
    return $out . '</select>';
}
