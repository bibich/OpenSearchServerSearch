
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- opensearchserver_config
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `opensearchserver_config`;

CREATE TABLE `opensearchserver_config`
(
    `name` VARCHAR(128) NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- opensearchserver_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `opensearchserver_product`;

CREATE TABLE `opensearchserver_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `disabled` TINYINT DEFAULT 0,
    `keywords` VARCHAR(255),
    PRIMARY KEY (`id`),
    INDEX `FI_oss_product_id` (`product_id`),
    CONSTRAINT `fk_oss_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
