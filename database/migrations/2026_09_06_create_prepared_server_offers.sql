-- Migration: create/update prepared server offers catalog
-- Date: 2026-09-06
-- Purpose: add the ready-server catalog used by economic/managed/advanced recommendations.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `Prepared_Server_Offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT NULL,
  `cpu_cores` int(11) NOT NULL,
  `ram_gb` int(11) NOT NULL,
  `usable_storage_gb` int(11) NOT NULL DEFAULT 0,
  `raw_storage_gb` int(11) NOT NULL DEFAULT 0,
  `gpu_memory_gb` int(11) NOT NULL DEFAULT 0,
  `generation_rank` int(11) NOT NULL DEFAULT 0,
  `expansion_score` int(11) NOT NULL DEFAULT 0,
  `performance_score` int(11) NOT NULL DEFAULT 0,
  `selected_components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`selected_components`)),
  `bullets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`bullets` is null or json_valid(`bullets`)),
  `stock_status` enum('Available','Limited','Unavailable') NOT NULL DEFAULT 'Available',
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `lead_time_days` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ready_offer_capacity` (`cpu_cores`,`ram_gb`,`usable_storage_gb`),
  KEY `idx_ready_offer_generation` (`generation_rank`,`expansion_score`),
  KEY `idx_ready_offer_stock` (`is_active`,`stock_status`,`stock_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Backward-compatible column additions for databases where the table already exists.
ALTER TABLE `Prepared_Server_Offers`
  ADD COLUMN IF NOT EXISTS `stock_status` enum('Available','Limited','Unavailable') NOT NULL DEFAULT 'Available' AFTER `bullets`,
  ADD COLUMN IF NOT EXISTS `stock_qty` int(11) NOT NULL DEFAULT 0 AFTER `stock_status`,
  ADD COLUMN IF NOT EXISTS `lead_time_days` int(11) NOT NULL DEFAULT 0 AFTER `stock_qty`;

ALTER TABLE `Prepared_Server_Offers`
  ADD KEY IF NOT EXISTS `idx_ready_offer_capacity` (`cpu_cores`,`ram_gb`,`usable_storage_gb`),
  ADD KEY IF NOT EXISTS `idx_ready_offer_generation` (`generation_rank`,`expansion_score`),
  ADD KEY IF NOT EXISTS `idx_ready_offer_stock` (`is_active`,`stock_status`,`stock_qty`);

INSERT INTO `Prepared_Server_Offers` (`id`, `title`, `description`, `icon`, `cpu_cores`, `ram_gb`, `usable_storage_gb`, `raw_storage_gb`, `gpu_memory_gb`, `generation_rank`, `expansion_score`, `performance_score`, `selected_components`, `bullets`, `stock_status`, `stock_qty`, `lead_time_days`) VALUES
(1, 'DL360 Gen9 اقتصادی', 'کم‌هزینه‌ترین سرور آماده‌ای که حداقل نیازهای پایه را پوشش می‌دهد و برای شروع کار مناسب است.', '🖨️', 12, 64, 2000, 4000, 0, 9, 35, 12064, '{"chassis_id":3,"cpu":{"id":55,"qty":1},"ram":{"id":20,"qty":2},"drives":[{"id":127,"qty":2,"raid":"1"}],"controller":null,"sas_expander":false,"gpu":null,"networks":[],"riser2":null,"riser3":null,"psu":{"id":13,"qty":2},"hbas":[],"optical_drives":[]}', '["ضعیف‌ترین گزینه‌ای که کار را راه می‌اندازد","مناسب سرویس‌های عمومی، حسابداری، CRM و تیم‌های کوچک","مصرف توان و هزینه اولیه کمتر"]', 'Available', 3, 1),
(2, 'DL380 Gen10 مدیریت‌شده', 'ترکیب استاندارد و متعادل با پردازنده دوگانه، رم بیشتر، RAID سخت‌افزاری و فضای توسعه مناسب برای چند سال آینده.', '🏢', 32, 256, 3840, 7680, 0, 10, 70, 32256, '{"chassis_id":1,"cpu":{"id":25,"qty":2},"ram":{"id":5,"qty":4},"drives":[{"id":31,"qty":4,"raid":"10"}],"controller":{"id":2},"sas_expander":false,"gpu":null,"networks":[{"id":2,"qty":1}],"riser2":{"id":3},"riser3":null,"psu":{"id":3,"qty":2},"hbas":[],"optical_drives":[]}', '["تعادل خوب بین هزینه، کارایی و پایداری","فضای توسعه مناسب برای رشد سازمان","مناسب دیتابیس، مجازی‌سازی سبک و سرویس‌های سازمانی"]', 'Available', 2, 2),
(3, 'ML110 Gen11 پیشرفته', 'گزینه نسل جدیدتر با ظرفیت ذخیره‌سازی بسیار بالاتر، NVMe، رم مناسب و GPU برای رشد آینده و بارهای سنگین‌تر.', '🚀', 32, 256, 25600, 51200, 24, 11, 85, 32256, '{"chassis_id":2,"cpu":{"id":37,"qty":1},"ram":{"id":14,"qty":4},"drives":[{"id":76,"qty":4,"raid":"10"}],"controller":{"id":12},"sas_expander":false,"gpu":{"id":7,"qty":1},"networks":[{"id":3,"qty":1}],"riser2":{"id":18},"riser3":null,"psu":{"id":11,"qty":2},"hbas":[],"optical_drives":[]}', '["نسل جدیدتر و مناسب‌تر برای ارتقای آینده","ظرفیت ذخیره‌سازی چندبرابر نیازهای معمول","آماده برای GPU، AI سبک، VDI یا بارهای پیشرفته"]', 'Limited', 1, 5)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `icon` = VALUES(`icon`),
  `cpu_cores` = VALUES(`cpu_cores`),
  `ram_gb` = VALUES(`ram_gb`),
  `usable_storage_gb` = VALUES(`usable_storage_gb`),
  `raw_storage_gb` = VALUES(`raw_storage_gb`),
  `gpu_memory_gb` = VALUES(`gpu_memory_gb`),
  `generation_rank` = VALUES(`generation_rank`),
  `expansion_score` = VALUES(`expansion_score`),
  `performance_score` = VALUES(`performance_score`),
  `selected_components` = VALUES(`selected_components`),
  `bullets` = VALUES(`bullets`),
  `stock_status` = VALUES(`stock_status`),
  `stock_qty` = VALUES(`stock_qty`),
  `lead_time_days` = VALUES(`lead_time_days`);

COMMIT;
