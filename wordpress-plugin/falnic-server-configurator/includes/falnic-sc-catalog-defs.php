<?php
/**
 * Catalog CRUD definitions.
 *
 * Drives the generic admin editor: labels, input types, validation hints,
 * list columns and searchable columns for every plugin table. Column keys map
 * 1:1 to the database schema (see falnic-sc-schema.php).
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'falnic_sc_catalog_defs' ) ) {
	/**
	 * All editable table definitions.
	 *
	 * @return array
	 */
	function falnic_sc_catalog_defs() {
		$currency = array(
			'type'    => 'select',
			'label'   => 'ارز',
			'options' => array( 'USD' => 'USD — دلار', 'EUR' => 'EUR — یورو', 'IRT' => 'IRT — تومان' ),
			'default' => 'USD',
			'list'    => false,
		);

		$compat_chassis = array(
			'type'  => 'json',
			'label' => 'شاسی‌های سازگار (JSON آرایه ID)',
			'help'  => 'خالی یا [] یعنی با همه شاسی‌ها سازگار است. مثال: [1,3]',
			'ltr'   => true,
		);

		return array(
			'chassis' => array(
				'label'          => 'شاسی‌های سرور',
				'singular'       => 'شاسی',
				'slug'           => 'falnic-sc-chassis',
				'description'    => 'شاسی‌های HPE ProLiant به همراه قوانین CPU، RAM، Storage و PCIe.',
				'search'         => array( 'part_number', 'model', 'generation', 'form_factor', 'cpu_socket_type' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'      => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'            => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true, 'help' => 'خالی = قیمت‌گذاری نشده' ),
					'currency'         => $currency,
					'brand'            => array( 'type' => 'text', 'label' => 'برند', 'default' => 'HPE' ),
					'family'           => array( 'type' => 'text', 'label' => 'خانواده', 'default' => 'ProLiant' ),
					'model'            => array( 'type' => 'text', 'label' => 'مدل', 'required' => true, 'list' => true ),
					'generation'       => array( 'type' => 'text', 'label' => 'نسل', 'required' => true, 'list' => true ),
					'form_factor'      => array( 'type' => 'text', 'label' => 'فرم‌فاکتور', 'required' => true, 'list' => true, 'help' => 'مثال: 1U، 2U، 4.5U Tower' ),
					'cpu_socket_type'  => array( 'type' => 'text', 'label' => 'سوکت CPU', 'required' => true, 'list' => true, 'ltr' => true, 'help' => 'مثال: LGA 3647 — باید با فیلد socket_type جدول CPUها یکی باشد' ),
					'max_cpus'         => array( 'type' => 'number', 'label' => 'حداکثر تعداد CPU', 'required' => true, 'default' => 2 ),
					'ram_generation'   => array( 'type' => 'text', 'label' => 'نسل رم', 'required' => true, 'list' => true, 'help' => 'DDR4 یا DDR5 — باید با memory_generation جدول RAMها یکی باشد' ),
					'max_ram_slots'    => array( 'type' => 'number', 'label' => 'حداکثر اسلات رم', 'required' => true ),
					'ram_slots_per_cpu' => array( 'type' => 'number', 'label' => 'اسلات رم به ازای هر CPU', 'default' => 12 ),
					'base_pcie_slots'  => array( 'type' => 'number', 'label' => 'اسلات‌های PCIe پایه', 'default' => 3 ),
					'base_x16_slots'   => array( 'type' => 'number', 'label' => 'اسلات‌های x16 پایه', 'default' => 1 ),
					'base_x8_slots'    => array( 'type' => 'number', 'label' => 'اسلات‌های x8 پایه', 'default' => 2 ),
					'max_pcie_slots'   => array( 'type' => 'number', 'label' => 'حداکثر اسلات PCIe', 'default' => 8 ),
					'default_network'  => array( 'type' => 'text', 'label' => 'کارت شبکه پیش‌فرض' ),
					'default_controller' => array( 'type' => 'text', 'label' => 'کنترلر پیش‌فرض' ),
					'gpu_support'      => array( 'type' => 'bool', 'label' => 'پشتیبانی از GPU', 'default' => 1 ),
					'max_psu_bays'     => array( 'type' => 'number', 'label' => 'تعداد جایگاه PSU', 'default' => 2 ),
					'base_power_watts' => array( 'type' => 'number', 'label' => 'توان پایه شاسی (وات)', 'default' => 100 ),
					'storage_rules'    => array(
						'type'    => 'json',
						'label'   => 'قوانین Storage (JSON)',
						'required' => true,
						'ltr'     => true,
						'rows'    => 4,
						'help'    => 'مثال: {"base_drive_type":"SFF","base_bays":8,"max_bays":24}',
						'default' => '{"base_drive_type":"SFF","base_bays":8,"max_bays":24}',
					),
					'cooling_rules'    => array(
						'type'    => 'json',
						'label'   => 'قوانین Cooling (JSON)',
						'required' => true,
						'ltr'     => true,
						'rows'    => 4,
						'help'    => 'مثال: {"default_qty":4,"default_type":"Standard","upgrade_to_high_perf_on":["NVMe","GPU"]}',
						'default' => '{"default_qty":4,"default_type":"Standard","upgrade_to_high_perf_on":[]}',
					),
				),
			),

			'cpus' => array(
				'label'          => 'پردازنده‌ها (CPU)',
				'singular'       => 'پردازنده',
				'slug'           => 'falnic-sc-cpus',
				'description'    => 'پردازنده‌های سازگار با سوکت‌های شاسی‌ها.',
				'search'         => array( 'part_number', 'model_name', 'generation', 'socket_type' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'           => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'                 => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'              => $currency,
					'socket_type'           => array( 'type' => 'text', 'label' => 'سوکت', 'required' => true, 'list' => true, 'ltr' => true ),
					'model_name'            => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'generation'            => array( 'type' => 'text', 'label' => 'نسل', 'list' => true ),
					'cores'                 => array( 'type' => 'number', 'label' => 'تعداد هسته', 'required' => true, 'list' => true ),
					'base_frequency_ghz'    => array( 'type' => 'decimal', 'label' => 'فرکانس پایه (GHz)', 'required' => true, 'list' => true, 'step' => '0.1' ),
					'tdp_watts'             => array( 'type' => 'number', 'label' => 'TDP (وات)', 'required' => true, 'list' => true ),
					'supported_ram_speed_mt' => array( 'type' => 'number', 'label' => 'حداکثر سرعت رم (MT/s)', 'required' => true ),
					'max_ram_capacity_tb'   => array( 'type' => 'decimal', 'label' => 'حداکثر ظرفیت رم (TB)', 'required' => true, 'step' => '0.1' ),
					'cooling_requirements' => array(
						'type'    => 'json',
						'label'   => 'الزامات Cooling (JSON)',
						'required' => true,
						'ltr'     => true,
						'rows'    => 3,
						'help'    => 'مثال: {"heatsink":"Standard","requires_high_perf_fan":false}',
						'default' => '{"heatsink":"Standard","requires_high_perf_fan":false}',
					),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'rams' => array(
				'label'          => 'رم‌ها (RAM)',
				'singular'       => 'ماژول رم',
				'slug'           => 'falnic-sc-rams',
				'description'    => 'ماژول‌های حافظه با نسل، سرعت و سازگاری CPU.',
				'search'         => array( 'part_number', 'model_name', 'memory_generation', 'ram_type' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'            => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'                  => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'               => $currency,
					'model_name'             => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'capacity_gb'            => array( 'type' => 'number', 'label' => 'ظرفیت (GB)', 'required' => true, 'list' => true ),
					'ram_type'               => array( 'type' => 'text', 'label' => 'نوع رم', 'required' => true, 'list' => true, 'help' => 'مثال: RDIMM، LRDIMM' ),
					'memory_generation'      => array(
						'type' => 'select', 'label' => 'نسل حافظه', 'required' => true, 'list' => true,
						'options' => array( 'DDR4' => 'DDR4', 'DDR5' => 'DDR5' ),
					),
					'speed_mt'               => array( 'type' => 'number', 'label' => 'سرعت (MT/s)', 'required' => true, 'list' => true ),
					'power_consumption_watts' => array( 'type' => 'number', 'label' => 'مصرف برق (وات)', 'default' => 5 ),
					'compatible_cpu_ids'     => array(
						'type'  => 'json',
						'label' => 'CPUهای سازگار (JSON آرایه ID)',
						'help'  => 'خالی یعنی با همه CPUهای هم‌نسل سازگار است. مثال: [25,37]',
						'ltr'   => true,
					),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'drives' => array(
				'label'          => 'هاردها و درایوها',
				'singular'       => 'درایو',
				'slug'           => 'falnic-sc-drives',
				'description'    => 'هاردهای HDD/SSD/NVMe با فرم‌فاکتور و interface.',
				'search'         => array( 'part_number', 'model_name', 'interface', 'drive_type' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'       => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'             => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'          => $currency,
					'model_name'        => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'form_factor'       => array(
						'type' => 'select', 'label' => 'فرم‌فاکتور', 'required' => true, 'list' => true,
						'options' => array( 'SFF' => 'SFF (2.5")', 'LFF' => 'LFF (3.5")' ),
					),
					'interface'         => array(
						'type' => 'select', 'label' => 'Interface', 'required' => true, 'list' => true,
						'options' => array( 'SAS' => 'SAS', 'SATA' => 'SATA', 'NVMe' => 'NVMe' ),
					),
					'drive_type'        => array(
						'type' => 'select', 'label' => 'نوع', 'required' => true, 'list' => true,
						'options' => array( 'HDD' => 'HDD', 'SSD' => 'SSD' ),
					),
					'capacity_gb'       => array( 'type' => 'number', 'label' => 'ظرفیت (GB)', 'required' => true, 'list' => true ),
					'power_consumption_watts' => array( 'type' => 'number', 'label' => 'مصرف برق (وات)', 'required' => true ),
					'requires_high_perf_fan' => array( 'type' => 'bool', 'label' => 'نیاز به فن High Performance', 'default' => 0, 'list' => true ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'controllers' => array(
				'label'          => 'کنترلرهای RAID',
				'singular'       => 'کنترلر',
				'slug'           => 'falnic-sc-controllers',
				'description'    => 'کنترلرهای Smart Array و نرم‌افزاری.',
				'search'         => array( 'part_number', 'model_name', 'form_factor' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'         => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'               => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'            => $currency,
					'model_name'          => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'form_factor'         => array(
						'type' => 'select', 'label' => 'فرم‌فاکتور', 'required' => true, 'list' => true,
						'options' => array( 'Type-a Modular (AROC)' => 'Type-a Modular (AROC)', 'Standup PCIe' => 'Standup PCIe' ),
					),
					'pcie_slots_used'     => array( 'type' => 'number', 'label' => 'اسلات‌های PCIe مصرفی', 'default' => 0, 'help' => 'AROC=0 و Standup PCIe=1' ),
					'supported_interfaces' => array(
						'type' => 'json', 'label' => 'Interfaceهای پشتیبانی‌شده (JSON)', 'required' => true, 'ltr' => true, 'rows' => 3,
						'help' => 'مثال: ["SATA","SAS"]',
						'default' => '["SATA","SAS"]',
					),
					'max_drives'          => array( 'type' => 'number', 'label' => 'حداکثر تعداد درایو', 'required' => true, 'list' => true ),
					'cache_gb'            => array( 'type' => 'decimal', 'label' => 'حافظه نهان (GB)', 'default' => 0, 'step' => '0.5' ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'gpus' => array(
				'label'          => 'کارت‌های گرافیک (GPU)',
				'singular'       => 'کارت گرافیک',
				'slug'           => 'falnic-sc-gpus',
				'description'    => 'کارت‌های شتاب‌دهنده گرافیکی و AI.',
				'search'         => array( 'part_number', 'model_name' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'          => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'                => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'             => $currency,
					'model_name'           => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'memory_gb'            => array( 'type' => 'number', 'label' => 'حافظه (GB)', 'required' => true, 'list' => true ),
					'tdp_watts'            => array( 'type' => 'number', 'label' => 'TDP (وات)', 'required' => true, 'list' => true ),
					'pcie_slots_used'      => array( 'type' => 'number', 'label' => 'اسلات‌های PCIe مصرفی', 'default' => 1 ),
					'requires_high_perf_fan' => array( 'type' => 'bool', 'label' => 'نیاز به فن High Performance', 'default' => 1 ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'networks' => array(
				'label'          => 'کارت‌های شبکه',
				'singular'       => 'کارت شبکه',
				'slug'           => 'falnic-sc-networks',
				'description'    => 'کارت‌های شبکه Standup PCIe و FlexibleLOM.',
				'search'         => array( 'part_number', 'model_name', 'port_type' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'       => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'             => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'          => $currency,
					'model_name'        => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'form_factor'       => array(
						'type' => 'select', 'label' => 'فرم‌فاکتور', 'required' => true, 'list' => true,
						'options' => array( 'FlexibleLOM' => 'FlexibleLOM', 'Standup PCIe' => 'Standup PCIe' ),
					),
					'pcie_slots_used'   => array( 'type' => 'number', 'label' => 'اسلات‌های PCIe مصرفی', 'default' => 0 ),
					'port_count'        => array( 'type' => 'number', 'label' => 'تعداد پورت', 'required' => true, 'list' => true ),
					'speed_gbps'        => array( 'type' => 'number', 'label' => 'سرعت (Gbps)', 'required' => true, 'list' => true ),
					'port_type'         => array(
						'type' => 'select', 'label' => 'نوع پورت', 'required' => true, 'list' => true,
						'options' => array( 'RJ45' => 'RJ45', 'SFP+' => 'SFP+', 'SFP28' => 'SFP28', 'QSFP28' => 'QSFP28' ),
					),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'risers' => array(
				'label'          => 'رایزرها',
				'singular'       => 'رایزر',
				'slug'           => 'falnic-sc-risers',
				'description'    => 'کیت‌های رایزر PCIe.',
				'search'         => array( 'model_name' ),
				'unique'         => 'model_name',
				'fields'         => array(
					'model_name'       => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'price'            => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'         => $currency,
					'x16_slots'        => array( 'type' => 'number', 'label' => 'تعداد اسلات x16', 'required' => true, 'list' => true, 'help' => 'اسلات‌های مخصوص GPU' ),
					'total_slots'      => array( 'type' => 'number', 'label' => 'مجموع اسلات‌ها', 'required' => true, 'list' => true ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'hbas' => array(
				'label'          => 'کارت‌های HBA',
				'singular'       => 'کارت HBA',
				'slug'           => 'falnic-sc-hbas',
				'description'    => 'کارت‌های اتصال به Storage خارجی.',
				'search'         => array( 'part_number', 'model_name' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'       => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'             => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'          => $currency,
					'model_name'        => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'pcie_slots_used'   => array( 'type' => 'number', 'label' => 'اسلات‌های PCIe مصرفی', 'default' => 1 ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'optical_drives' => array(
				'label'          => 'درایوهای نوری',
				'singular'       => 'درایو نوری',
				'slug'           => 'falnic-sc-optical-drives',
				'description'    => 'درایوهای DVD.',
				'search'         => array( 'part_number', 'model_name' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'            => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'                  => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'               => $currency,
					'model_name'             => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'power_consumption_watts' => array( 'type' => 'number', 'label' => 'مصرف برق (وات)', 'default' => 15 ),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'psus' => array(
				'label'          => 'پاورها (PSU)',
				'singular'       => 'پاور',
				'slug'           => 'falnic-sc-psus',
				'description'    => 'منابع تغذیه با توان و راندمان.',
				'search'         => array( 'part_number', 'model_name' ),
				'unique'         => 'part_number',
				'fields'         => array(
					'part_number'       => array( 'type' => 'text', 'label' => 'پارت نامبر', 'required' => true, 'list' => true, 'ltr' => true ),
					'price'             => array( 'type' => 'decimal', 'label' => 'قیمت', 'list' => true ),
					'currency'          => $currency,
					'model_name'        => array( 'type' => 'text', 'label' => 'نام مدل', 'required' => true, 'list' => true ),
					'wattage'           => array( 'type' => 'number', 'label' => 'توان (وات)', 'required' => true, 'list' => true ),
					'efficiency'        => array(
						'type' => 'select', 'label' => 'راندمان', 'required' => true, 'list' => true,
						'options' => array( 'Platinum' => 'Platinum', 'Titanium' => 'Titanium', 'Gold' => 'Gold' ),
					),
					'hot_plug'          => array( 'type' => 'bool', 'label' => 'Hot-plug', 'default' => 1 ),
					'input_voltage_support' => array(
						'type' => 'json', 'label' => 'ولتاژهای ورودی (JSON)', 'required' => true, 'ltr' => true, 'rows' => 3,
						'help' => 'مثال: ["100-127V","200-240V"]',
						'default' => '["100-127V","200-240V"]',
					),
					'compatible_chassis_ids' => $compat_chassis,
				),
			),

			'offers' => array(
				'label'          => 'پیشنهادهای آماده',
				'singular'       => 'پیشنهاد آماده',
				'slug'           => 'falnic-sc-offers',
				'description'    => 'سرورهای آماده مسیر راهنمایی (اقتصادی / مدیریت‌شده / پیشرفته).',
				'search'         => array( 'title', 'description' ),
				'fields'         => array(
					'title'         => array( 'type' => 'text', 'label' => 'عنوان', 'required' => true, 'list' => true ),
					'description'   => array( 'type' => 'textarea', 'label' => 'توضیحات', 'rows' => 3 ),
					'icon'          => array( 'type' => 'text', 'label' => 'آیکون (اموجی)', 'list' => true ),
					'cpu_cores'     => array( 'type' => 'number', 'label' => 'مجموع هسته', 'required' => true, 'list' => true ),
					'ram_gb'        => array( 'type' => 'number', 'label' => 'مجموع رم (GB)', 'required' => true, 'list' => true ),
					'usable_storage_gb' => array( 'type' => 'number', 'label' => 'Storage قابل استفاده (GB)', 'required' => true, 'list' => true ),
					'raw_storage_gb' => array( 'type' => 'number', 'label' => 'Storage خام (GB)', 'default' => 0 ),
					'gpu_memory_gb' => array( 'type' => 'number', 'label' => 'حافظه GPU (GB)', 'default' => 0, 'list' => true ),
					'generation_rank' => array( 'type' => 'number', 'label' => 'رتبه نسل', 'default' => 0, 'help' => 'مثلاً 9 برای Gen9' ),
					'expansion_score' => array( 'type' => 'number', 'label' => 'امتیاز توسعه‌پذیری', 'default' => 0 ),
					'performance_score' => array( 'type' => 'number', 'label' => 'امتیاز کارایی', 'default' => 0 ),
					'selected_components' => array(
						'type' => 'json', 'label' => 'قطعات انتخابی (JSON)', 'required' => true, 'ltr' => true, 'rows' => 10,
						'help' => 'ساختار دقیق ستون selected_components در دیتابیس. chassis_id، cpu، ram، drives، controller، gpu، networks، riser2، riser3، psu، hbas، optical_drives',
					),
					'bullets'       => array(
						'type' => 'json', 'label' => 'بولت‌های نمایشی (JSON آرایه رشته)', 'ltr' => true, 'rows' => 4,
						'help' => 'مثال: ["تعادل خوب بین هزینه و کارایی"]',
						'default' => '[]',
					),
					'stock_status'  => array(
						'type' => 'select', 'label' => 'وضعیت موجودی', 'required' => true, 'list' => true,
						'options' => array( 'Available' => 'موجود', 'Limited' => 'محدود', 'Unavailable' => 'ناموجود' ),
					),
					'stock_qty'     => array( 'type' => 'number', 'label' => 'تعداد موجود', 'default' => 0, 'list' => true ),
					'lead_time_days' => array( 'type' => 'number', 'label' => 'زمان تأمین (روز)', 'default' => 0, 'list' => true ),
					'is_active'     => array( 'type' => 'bool', 'label' => 'فعال', 'default' => 1, 'list' => true ),
				),
			),
		);
	}
}
