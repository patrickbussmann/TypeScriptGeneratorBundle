<?php

namespace Janit\TypeScriptGeneratorBundle\Command;

use Janit\TypeScriptGeneratorBundle\Parser\Visitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class GenerateCommand extends Command
{
	protected static $defaultName = 'typescript:generate-interfaces';

	/**
	 * @var ParameterBagInterface
	 */
	private $parameterBag;

	/**
	 * @param string|null $name The name of the command; passing null means it must be set in configure()
	 * @param ParameterBagInterface $parameterBag
	 */
	public function __construct(string $name = null, ParameterBagInterface $parameterBag = null)
	{
		parent::__construct($name);
		$this->parameterBag = $parameterBag;
	}

	protected function configure()
    {
        $this
            ->setDescription('Generate TypeScript interfaces from PHP classes in a directory')
            ->addArgument(
                'fromDir',
                InputArgument::REQUIRED,
                'The directory to scan for suitable classes'
            )
            ->addArgument(
                'toDir',
                InputArgument::OPTIONAL,
                'Where to export generated classes'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $fromDir = $input->getArgument('fromDir');
        $toDir = $input->getArgument('toDir');

        if(!$toDir){
            $toDir = 'typescript';
        }

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $traverser = new NodeTraverser();

        $fs = new Filesystem();
        $finder = new Finder();
        $finder->files('*.php')->in( $projectDir . '/' . $fromDir);

        foreach ($finder as $file) {

            $visitor = new Visitor();
            $traverser->addVisitor($visitor);
            $code = $file->getContents();

            try {

                $stmts = $parser->parse($code);
                $stmts = $traverser->traverse($stmts);

                if($visitor->getOutput()){
                    $targetFile = $toDir . '/' . str_replace( '.php','.d.ts', $file->getFilename());
                    $fs->dumpFile($targetFile,$visitor->getOutput());
                    $output->writeln('created interface ' . $targetFile);
                }

            } catch (\ParseError $e) {
                $output->writeln('Parse error: ' .$e->getMessage());
            }

        }

	    return Command::SUCCESS;
    }
}