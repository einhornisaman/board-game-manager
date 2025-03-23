<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap">
    <h1>Import Game</h1>
    <p>Enter a BoardGameGeek ID to import a game.</p>
    
    <?php if (isset($result)) : ?>
        <?php if ($result['success']) : ?>
            <div class="notice notice-success">
                <p>Game "<?php echo esc_html($result['name']); ?>" <?php echo esc_html($result['action']); ?> successfully!</p>
                <div style="margin-top: 15px;">
                    <strong>Details:</strong><br>
                    Year: <?php echo esc_html($result['year_published']); ?><br>
                    Publisher: <?php echo esc_html($result['publisher']); ?><br>
                    Designer: <?php echo esc_html($result['designer']); ?><br>
                </div>
            </div>
        <?php else : ?>
            <div class="notice notice-error">
                <p>Error: <?php echo esc_html($result['message']); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <form method="post" style="margin-bottom: 30px;">
        <input type="number" name="bgg_id" placeholder="BGG ID (e.g. 174430)" required>
        <button type="submit" class="button button-primary">Import Game</button>
    </form>
    
    <p>Examples of popular games:</p>
    <ul>
        <li>Catan: 13</li>
        <li>Ticket to Ride: 9209</li>
        <li>Pandemic: 30549</li>
        <li>7 Wonders: 68448</li>
        <li>Azul: 230802</li>
    </ul>
</div>