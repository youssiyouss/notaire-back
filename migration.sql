
Warning: Version warning: Imagick was compiled against ImageMagick version 1808 but version 1809 is loaded. Imagick will run but may behave surprisingly in Unknown on line 0

   INFO  Running migrations.  

  2025_03_30_015206_create_contracts_table .........................................................................................................  
  ⇂ create table `contracts` (`id` bigint unsigned not null auto_increment primary key, `template_id` bigint unsigned not null, `content` longtext not null, `status` varchar(255) not null, `created_by` bigint unsigned not null, `updated_by` bigint unsigned null, `pdf_path` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci'  
  ⇂ alter table `contracts` add constraint `contracts_template_id_foreign` foreign key (`template_id`) references `contract_templates` (`id`) on delete cascade  
  ⇂ alter table `contracts` add constraint `contracts_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete cascade  
  ⇂ alter table `contracts` add constraint `contracts_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null  
  2025_06_07_004638_add_price_fields_to_contract_templates_table ...................................................................................  
  ⇂ alter table `contract_templates` add `original` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `copy` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `documentation` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `publication` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `consultation` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `consultationFee` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `workFee` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `others` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `stamp` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `registration` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `advertisement` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `rkm` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `announcements` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `deposit` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `boal` decimal(10, 2) null  
  ⇂ alter table `contract_templates` add `registration_or_cancellation` decimal(10, 2) null  

