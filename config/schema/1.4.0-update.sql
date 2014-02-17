--
-- This file is part of the DreamFactory Services Platform(tm) (DSP)
--
-- DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
-- Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
--
-- Licensed under the Apache License, Version 2.0 (the "License");
-- you may not use this file except in compliance with the License.
-- You may obtain a copy of the License at
--
-- http://www.apache.org/licenses/LICENSE-2.0
--
-- Unless required by applicable law or agreed to in writing, software
-- distributed under the License is distributed on an "AS IS" BASIS,
-- WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
-- See the License for the specific language governing permissions and
-- limitations under the License.
--

--
-- DSP v1.2.x database update script for MySQL
--

DROP TABLE IF EXISTS `df_sys_account_provider`;
DROP TABLE IF EXISTS `df_sys_service_account`;
DROP TABLE IF EXISTS `df_sys_portal_account`;

-- Remove old column if exists
ALTER TABLE `df_sys_provider` DROP COLUMN `base_provider`;

-- Remove old indexes
ALTER TABLE `df_sys_provider` DROP INDEX `fk_provider_base_provider_id`;
ALTER TABLE `df_sys_provider_user` DROP INDEX `fk_provider_user_user_id`, DROP INDEX `fk_provider_user_provider_id`, DROP INDEX `undx_provider_user_all_user_ids`;

--	Unique index on portal accounts
ALTER TABLE `df_sys_provider`
ADD CONSTRAINT `fk_provider_base_provider_id`
FOREIGN KEY (`base_provider_id`)
REFERENCES `df_sys_provider` (`id`)
		ON DELETE CASCADE;

ALTER TABLE `df_sys_provider_user`
ADD CONSTRAINT `fk_provider_user_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `df_sys_user` (`id`)
		ON DELETE CASCADE;

ALTER TABLE `df_sys_provider_user`
ADD CONSTRAINT `fk_provider_user_provider_id`
FOREIGN KEY (`provider_id`)
REFERENCES `df_sys_provider` (`id`)
		ON DELETE CASCADE;

--	A unique index on the provider user (credentials storage)
CREATE UNIQUE INDEX `undx_provider_user_all_user_ids` ON `df_sys_provider_user` (`user_id`, `provider_id`, `provider_user_id`);
