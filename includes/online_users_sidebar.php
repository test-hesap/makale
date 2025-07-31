<?php
// Çevrimiçi kullanıcıları göstermek için kenar çubuğu bileşeni

// Çevrimiçi kullanıcıları al
$online_users = getOnlineUsers(10); // En fazla 10 kişi göster
$total_online = getTotalOnlineUsers();
$guest_count = getGuestCount();
$bot_count = getBotCount();
$online_bots = getOnlineBots(100); // Tüm botları al (çok fazla olmaması için limit 100)

// Botları grupla ve say
$bot_groups = [];
if (count($online_bots) > 0) {
    foreach ($online_bots as $bot) {
        $bot_name = $bot['bot_name'];
        if (!isset($bot_groups[$bot_name])) {
            $bot_groups[$bot_name] = 1;
        } else {
            $bot_groups[$bot_name]++;
        }
    }
}
?>

<!-- Çevrimiçi Üyeler Kutusu -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
    <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-white">
        <?php echo __('sidebar_online_users'); ?>
    </h3>
    
    <div class="online-users-list">
        <?php if (count($online_users) > 0): ?>
            <div class="text-blue-500">
                <?php 
                $user_links = [];
                foreach ($online_users as $user) {
                    $user_links[] = '<a href="/uyeler/' . $user['username'] . '" class="hover:text-blue-700">' . htmlspecialchars($user['username']) . '</a>';
                }
                
                // Üyeleri göster
                echo implode(', ', $user_links);
                
                // Kalan üye sayısı
                if ($total_online > count($online_users)) {
                    echo '<span class="text-gray-500 mx-1">... ve ' . ($total_online - count($online_users)) . ' diğer üye</span>';
                }
                
                // Botları göster (eğer varsa)
                if (count($bot_groups) > 0) {
                    echo '<span class="text-gray-500 mx-1">, </span>';
                    
                    $bot_display = [];
                    foreach ($bot_groups as $bot_name => $count) {
                        $bot_display[] = htmlspecialchars($bot_name) . ($count > 1 ? ' (' . $count . ')' : '');
                    }
                    echo '<span class="text-gray-500">' . implode(', ', $bot_display) . '</span>';
                }
                ?>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                <?php echo __('sidebar_total_online'); ?>: <?php echo ($total_online + $guest_count + $bot_count); ?> (<?php echo __('sidebar_members'); ?>: <?php echo $total_online; ?>, <?php echo __('sidebar_guests'); ?>: <?php echo $guest_count; ?>, <?php echo __('sidebar_bots'); ?>: <?php echo $bot_count; ?>)
            </div>
        <?php else: ?>
            <?php if ($guest_count > 0 || $bot_count > 0): ?>
                <p class="text-gray-500 text-sm"><?php echo __('no_online_members'); ?></p>
                
                <?php if (count($bot_groups) > 0): ?>
                    <div class="text-gray-600 text-sm mt-1">
                        <?php 
                        $bot_display = [];
                        foreach ($bot_groups as $bot_name => $count) {
                            $bot_display[] = htmlspecialchars($bot_name) . ($count > 1 ? ' (' . $count . ')' : '');
                        }
                        echo '<span class="text-gray-500">' . implode(', ', $bot_display) . '</span>';
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-xs text-gray-500 mt-2">
                    <?php echo __('sidebar_total_online'); ?>: <?php echo ($guest_count + $bot_count); ?> (<?php echo __('sidebar_members'); ?>: 0, <?php echo __('sidebar_guests'); ?>: <?php echo $guest_count; ?>, <?php echo __('sidebar_bots'); ?>: <?php echo $bot_count; ?>)
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm"><?php echo __('no_online_visitors'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
