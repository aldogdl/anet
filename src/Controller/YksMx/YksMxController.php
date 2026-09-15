<?php

namespace App\Controller\YksMx;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/ynk-wh')]
class YksMxController extends AbstractController
{
	#[Route('/', methods: ['get', 'post'])]
	public function indexWh(Request $req): Response
	{
	  if($req->getMethod() == 'POST' ) {
			$data = $req->getContent();
			if($data) {
				$data = json_decode($data, true);
				if(isset($data['event']) && $data['event'] == 'product.restored') {
					$data['ok'] = 'WH Recibido';
				} else if(isset($data['event']) && $data['event'] == 'product.soft_deleted') {
					$data['ok'] = 'WH Recibido';
				} else {
					$data['ok'] = 'WH Recibido';
					$data['event_failed'] = 'Evento no reconocido';
				}
			}
			file_put_contents('prueba_hook.json', json_encode($data));
		}
		return $this->json(['Yonkeros' => 'Bienvenido']);
	}

	#[Route('/presence', name: 'yks_presence', methods: ['GET'])]
	public function presence(): Response
	{
		$presenceDir = $this->getParameter('kernel.project_dir') . '/public_html/presence';

		// Definir el rango de 15 días: 2026-09-08 al 2026-09-22 inclusive
		$startDate = new \DateTimeImmutable('2026-09-08');
		$endDate = new \DateTimeImmutable('2026-09-22');

		$days = [];
		$period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->modify('+1 day'));
		foreach ($period as $date) {
			$days[] = [
				'key' => $date->format('Y-m-d'),
				'label' => $date->format('d/m'),
			];
		}

		$rows = [];
		if (is_dir($presenceDir)) {
			$files = glob($presenceDir . '/*.json');
			if ($files !== false) {
				foreach ($files as $filePath) {
					$fileName = basename($filePath, '.json');
					$firstDash = strpos($fileName, '-');
					if ($firstDash === false) {
						$empresa = $fileName;
						$usuario = '';
					} else {
						$empresa = substr($fileName, 0, $firstDash);
						$usuario = substr($fileName, $firstDash + 1);
					}

					$content = @file_get_contents($filePath);
					if ($content === false) {
						continue;
					}

					$records = @json_decode($content, true);
					if (!is_array($records)) {
						continue;
					}

					// attendance: [ 'Y-m-d' => ['time' => 'HH:mm', 'timestamp' => int] ]
					$attendance = [];
					foreach ($records as $entry) {
						if (!isset($entry['at']) || !is_string($entry['at'])) {
							continue;
						}

						try {
							$dt = new \DateTimeImmutable($entry['at']);
						} catch (\Exception $e) {
							continue;
						}

						$dayKey = $dt->format('Y-m-d');
						$timestamp = $dt->getTimestamp();

						// Si existen varios registros el mismo día, conservar únicamente el más reciente
						if (!isset($attendance[$dayKey]) || $timestamp > $attendance[$dayKey]['timestamp']) {
							$attendance[$dayKey] = [
								'time' => $dt->format('H:i'),
								'timestamp' => $timestamp,
							];
						}
					}

					$rows[] = [
						'empresa' => $empresa,
						'usuario' => $usuario,
						'attendance' => $attendance,
					];
				}
			}
		}

		// Ordenar filas por empresa y luego por usuario
		usort($rows, function ($a, $b) {
			$cmpEmpresa = strcasecmp($a['empresa'], $b['empresa']);
			if ($cmpEmpresa !== 0) {
				return $cmpEmpresa;
			}
			return strcasecmp($a['usuario'], $b['usuario']);
		});

		return $this->render('presence/index.html.twig', [
			'days' => $days,
			'rows' => $rows,
			'periodStart' => '08/09/2026',
			'periodEnd' => '22/09/2026',
		]);
	}
}
