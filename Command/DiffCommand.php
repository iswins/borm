<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 15:05
 */


namespace Iswin\Borm\Command;

use Iswin\Borm\Fixtures\HlBlock;
use Iswin\Borm\OrmProvider\BitrixSchemaProvider;
use Doctrine\Bundle\MigrationsBundle\Command\MigrationsDiffDoctrineCommand as ParentDiffCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\MigrationsBundle\Command\Helper;
use Doctrine\Bundle\MigrationsBundle\Command\DoctrineCommand;
use Doctrine\DBAL\Migrations\Provider\SchemaProviderInterface;
use Doctrine\DBAL\Migrations\Provider\OrmSchemaProvider;
use Doctrine\DBAL\Version as DbalVersion;
use Doctrine\DBAL\Migrations\Configuration\Configuration;

class DiffCommand extends ParentDiffCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('borm:migrations:diff');
    }

    public function execute (InputInterface $input, OutputInterface $output)
    {
        Helper\DoctrineCommandHelper::setApplicationHelper($this->getApplication(), $input);

        $configuration = $this->getMigrationConfiguration($input, $output);
        DoctrineCommand::configureMigrations($this->getApplication()->getKernel()->getContainer(), $configuration);


        $isDbalOld     = (DbalVersion::compare('2.2.0') > 0);
        $configuration = $this->getMigrationConfiguration($input, $output);

        $this->loadCustomTemplate($configuration, $output);

        $conn     = $configuration->getConnection();
        $platform = $conn->getDatabasePlatform();

        if ($filterExpr = $input->getOption('filter-expression')) {
            if ($isDbalOld) {
                throw new \InvalidArgumentException('The "--filter-expression" option can only be used as of Doctrine DBAL 2.2');
            }

            $conn->getConfiguration()
                ->setFilterSchemaAssetsExpression($filterExpr);
        }

        /** @var \Doctrine\DBAL\Schema\Schema $fromSchema */
        $fromSchema = $conn->getSchemaManager()->createSchema();

        /** @var \Doctrine\DBAL\Schema\Schema $toSchema */
        $toSchema   = $this->getSchemaProvider()->createSchema();
        /** @var HlBlock[] $bitrixFixtures */
        $bitrixFixtures = $this->getSchemaProvider()->getBitrixFixtures();

        /** @todo обработка удаления свойств !!!!!!!! (считываем таблицы из битрикса, считываем их свойства, сравниваем с Fixture) */

        //Not using value from options, because filters can be set from config.yml
        if ( ! $isDbalOld && $filterExpr = $conn->getConfiguration()->getFilterSchemaAssetsExpression()) {
            foreach ($toSchema->getTables() as $table) {
                $tableName = $table->getName();
                if ( ! preg_match($filterExpr, $this->resolveTableName($tableName))) {
                    $toSchema->dropTable($tableName);
                }
            }
        }

        $up   = $this->buildCodeFromSql(
            $configuration,
            $fromSchema->getMigrateToSql($toSchema, $platform),
            $input->getOption('formatted'),
            $input->getOption('line-length')
        );


        foreach ($bitrixFixtures as $bitrixFixture) {
            $ups = $bitrixFixture->getUpMigrations($conn);
            foreach ($ups as $upItem) {
                $up .= "\n". sprintf("\$this->addSql(\"%s\");", $upItem);
            }
        }



        $down = $this->buildCodeFromSql(
            $configuration,
            $fromSchema->getMigrateFromSql($toSchema, $platform),
            $input->getOption('formatted'),
            $input->getOption('line-length')
        );

        foreach ($bitrixFixtures as $bitrixFixture) {
            $downs = $bitrixFixture->getDownMigrations($conn);
            foreach ($downs as $downItem) {
                $down .= "\n". sprintf("\$this->addSql(\"%s\");", $downItem);
            }
        }


        if ( ! $up && ! $down) {
            $output->writeln('No changes detected in your mapping information.');

            return;
        }

        $version = $configuration->generateVersionNumber();
        $path    = $this->generateMigration($configuration, $input, $version, $up, $down);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
        $output->writeln(file_get_contents($path), OutputInterface::VERBOSITY_VERBOSE);
    }


    private function buildCodeFromSql(Configuration $configuration, array $sql, $formatted = false, $lineLength = 120)
    {
        $currentPlatform = $configuration->getConnection()->getDatabasePlatform()->getName();
        $code            = [];
        foreach ($sql as $query) {
            if (stripos($query, $configuration->getMigrationsTableName()) !== false) {
                continue;
            }

            if ($formatted) {
                if ( ! class_exists('\SqlFormatter')) {
                    throw new \InvalidArgumentException(
                        'The "--formatted" option can only be used if the sql formatter is installed.' .
                        'Please run "composer require jdorn/sql-formatter".'
                    );
                }

                $maxLength = $lineLength - 18 - 8; // max - php code length - indentation

                if (strlen($query) > $maxLength) {
                    $query = \SqlFormatter::format($query, false);
                }
            }

            $code[] = sprintf("\$this->addSql(%s);", var_export($query, true));
        }

        if ( ! empty($code)) {
            array_unshift(
                $code,
                sprintf(
                    "\$this->abortIf(\$this->connection->getDatabasePlatform()->getName() !== %s, %s);",
                    var_export($currentPlatform, true),
                    var_export(sprintf("Migration can only be executed safely on '%s'.", $currentPlatform), true)
                ),
                ""
            );
        }

        return implode("\n", $code);
    }

    private function getSchemaProvider()
    {
        if ( ! $this->schemaProvider) {
            $this->schemaProvider = new BitrixSchemaProvider($this->getHelper('entityManager')->getEntityManager());
        }

        return $this->schemaProvider;
    }

    /**
     * Resolve a table name from its fully qualified name. The `$name` argument
     * comes from Doctrine\DBAL\Schema\Table#getName which can sometimes return
     * a namespaced name with the form `{namespace}.{tableName}`. This extracts
     * the table name from that.
     *
     * @param   string $name
     * @return  string
     */
    private function resolveTableName($name)
    {
        $pos = strpos($name, '.');

        return false === $pos ? $name : substr($name, $pos + 1);
    }
}