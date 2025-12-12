import {
	Button,
	Card,
	CardBody,
	Flex,
	FlexItem,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { render, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const settings = window.MockomaticSettings || {};
const restUrl = settings.restUrl || '';
const restNonce = settings.nonce || '';
const defaults = settings.defaults || {};
const textModels = settings.textModels || {};
const imageModels = settings.imageModels || {};
const baseRestUrl = restUrl.replace(/\/$/, '');
const adminEditBase = settings.adminEditBase || '';
const siteUrl = settings.siteUrl || '';
const siteBaseUrl = siteUrl.replace(/\/$/, '');

const defaultFormState = {
	posts: Number.isFinite(defaults.posts) ? defaults.posts : 5,
	pages: Number.isFinite(defaults.pages) ? defaults.pages : 2,
	categories: typeof defaults.categories === 'boolean' ? defaults.categories : true,
	tags: typeof defaults.tags === 'boolean' ? defaults.tags : true,
	generateImages: !!defaults.images,
	instructions: defaults.instructions || '',
	textModel: defaults.textModel || Object.keys(textModels)[0] || '',
	imageModel: defaults.imageModel || Object.keys(imageModels)[0] || '',
};

const postJSON = (endpoint, data) =>
	window
		.fetch(`${baseRestUrl}${endpoint}`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify(data),
		})
		.then((response) => {
			if (!response.ok) {
				return response
					.json()
					.catch(() => {
						throw new Error(`HTTP ${response.status}`);
					})
					.then((err) => {
						const message = err && err.message ? err.message : `HTTP ${response.status}`;
						throw new Error(message);
					});
			}

			return response.json();
		});

const GenerateContentApp = () => {
	const [formState, setFormState] = useState(defaultFormState);
	const [logs, setLogs] = useState([]);
	const [progress, setProgress] = useState({ value: 0, label: '' });
	const [isRunning, setIsRunning] = useState(false);
	const [isPaused, setIsPaused] = useState(false);
	const [showForm, setShowForm] = useState(false);
	const [errorMessage, setErrorMessage] = useState('');
	const [tasks, setTasks] = useState([]);
	const [hasStarted, setHasStarted] = useState(false);
	const pauseRef = useRef(false);

	const textModelOptions = useMemo(
		() =>
			Object.entries(textModels).map(([value, label]) => ({
				value,
				label,
			})),
		[textModels]
	);
	const imageModelOptions = useMemo(
		() =>
			Object.entries(imageModels).map(([value, label]) => ({
				value,
				label,
			})),
		[imageModels]
	);
	const imageModelOptionsWithOff = useMemo(
		() => [
			{ value: 'off', label: __('Off', 'mockomatic') },
			...imageModelOptions,
		],
		[imageModelOptions]
	);

	const logMessage = (text, isError = false) => {
		setLogs((prev) => [...prev, { text, isError }]);
	};

	const updateNumber = (field, value) => {
		const parsed = parseInt(value, 10);
		setFormState((prev) => ({
			...prev,
			[field]: Number.isNaN(parsed) ? 0 : parsed,
		}));
	};

	const updateField = (field, value) => {
		setFormState((prev) => ({
			...prev,
			[field]: value,
		}));
	};

	const handleImageModelChange = (value) => {
		if (value === 'off') {
			setFormState((prev) => ({
				...prev,
				generateImages: false,
			}));
			return;
		}

		setFormState((prev) => ({
			...prev,
			imageModel: value,
			generateImages: true,
		}));
	};

	const updateTaskStatus = (index, status, note = '', extra = {}) => {
		setTasks((prev) =>
			prev.map((task, idx) =>
				idx === index
					? {
							...task,
							status,
							note,
							...extra,
					  }
					: task
			)
		);
	};

	const pauseGeneration = () => {
		if (!isRunning || isPaused) {
			return;
		}
		setIsPaused(true);
		pauseRef.current = true;
		logMessage(__('Pausing after the current item…', 'mockomatic'));
		setProgress((prev) => ({ ...prev, label: __('Pausing…', 'mockomatic') }));
	};

	const resumeGeneration = () => {
		if (!isRunning || !isPaused) {
			return;
		}
		setIsPaused(false);
		pauseRef.current = false;
		logMessage(__('Resuming…', 'mockomatic'));
		setProgress((prev) => ({ ...prev, label: __('Resuming…', 'mockomatic') }));
	};

	const waitWhilePaused = async () => {
		if (!pauseRef.current) {
			return;
		}
		logMessage(__('Paused. Click “Resume” to continue.', 'mockomatic'));
		setProgress((prev) => ({ ...prev, label: __('Paused', 'mockomatic') }));

		// Poll until the user resumes.
		while (pauseRef.current) {
			// eslint-disable-next-line no-await-in-loop
			await new Promise((resolve) => setTimeout(resolve, 300));
		}
	};

	const startGeneration = async (event) => {
		event.preventDefault();

		setLogs([]);
		setErrorMessage('');
		setProgress({ value: 0, label: '' });
		setTasks([]);
		setIsPaused(false);
		pauseRef.current = false;
		setShowForm(false);
		setHasStarted(true);

		if (formState.posts <= 0 && formState.pages <= 0) {
			setErrorMessage(__('Please request at least one post or page.', 'mockomatic'));
			return;
		}

		setIsRunning(true);

		try {
			logMessage(__('Requesting titles from AI…', 'mockomatic'));
			setProgress({ value: 3, label: __('Requesting titles…', 'mockomatic') });

			const titlesResponse = await postJSON('/titles', {
				posts: formState.posts,
				pages: formState.pages,
				instructions: formState.instructions,
				model: formState.textModel,
				generate_images: !!formState.generateImages,
			});

			const postTitles = titlesResponse.posts || [];
			const pageTitles = titlesResponse.pages || [];

			const tasks = [
				...postTitles.map((item) => ({
					type: 'post',
					title: item.title,
					categories: item.categories || [],
					tags: item.tags || [],
					illustration_description: formState.generateImages ? item.illustration_description || '' : '',
				})),
				...pageTitles.map((item) => ({
					type: 'page',
					title: item.title,
				})),
			];

			if (!tasks.length) {
				throw new Error(__('No titles returned by AI.', 'mockomatic'));
			}

			setTasks(
				tasks.map((task) => ({
					...task,
					status: 'pending',
					note: '',
				}))
			);

			logMessage(__('Titles ready. Generating content now…', 'mockomatic'));
			setProgress({ value: 8, label: __('Generating content…', 'mockomatic') });

			for (let index = 0; index < tasks.length; index += 1) {
				// Wait here if the user paused the run.
				// eslint-disable-next-line no-await-in-loop
				await waitWhilePaused();

				const task = tasks[index];
				const label =
					task.type === 'post'
						? __('Generating post', 'mockomatic')
						: __('Generating page', 'mockomatic');

				logMessage(`${label}: ${task.title}`);
				updateTaskStatus(index, 'in-progress');

				const payload = {
					title: task.title,
					post_type: task.type,
					instructions: formState.instructions,
					model: formState.textModel,
					generate_image: !!formState.generateImages,
					image_model: formState.imageModel,
					categories: task.type === 'post' && formState.categories ? task.categories : [],
					tags: task.type === 'post' && formState.tags ? task.tags : [],
					illustration_description:
						task.type === 'post' && formState.generateImages ? task.illustration_description : '',
				};

				try {
					const result = await postJSON('/post', payload);

					if (result.attachment_id) {
						logMessage(
							/* translators: %s is the title of the item that received a featured image. */
							sprintf(__('Featured image set for %s', 'mockomatic'), result.title || task.title)
						);
					}

					if (result.image_error) {
						logMessage(
							`${__('Image error for', 'mockomatic')} ${result.title || task.title}: ${
								result.image_error
							}`,
							true
						);
					}

					updateTaskStatus(index, 'done', '', {
						post_id: result.post_id,
						post_type: result.post_type,
					});
				} catch (err) {
					logMessage(`${__('Error:', 'mockomatic')} ${err.message}`, true);
					updateTaskStatus(index, 'error', err.message);
				}

				const percent = 8 + Math.round(((index + 1) / tasks.length) * 82);
				setProgress({ value: percent, label: `${label} (${index + 1}/${tasks.length})` });
			}

			setProgress({ value: 100, label: __('Generation completed.', 'mockomatic') });
			logMessage(__('Generation completed.', 'mockomatic'));
		} catch (err) {
			setErrorMessage(err.message || __('Something went wrong.', 'mockomatic'));
			setProgress({ value: 100, label: __('Error', 'mockomatic') });
			logMessage(`${__('Error:', 'mockomatic')} ${err.message}`, true);
		} finally {
			setIsRunning(false);
			setIsPaused(false);
		}
	};

	if (!restUrl) {
		return (
			<Notice status="error" isDismissible={false}>
				{__('Mockomatic REST endpoint unavailable.', 'mockomatic')}
			</Notice>
		);
	}

	return (
		<div className="mockomatic-app">
			{!hasStarted && (
				<Card className="mockomatic-hero-card">
					<CardBody>
						<div className="mockomatic-hero">
							<div className="mockomatic-hero-heading">
								<h2 className="mockomatic-hero-title">{__('Generate Demo Content', 'mockomatic')}</h2>
							</div>
							<div className="mockomatic-cta">
								<button
									type="button"
									className="button button-primary button-hero mockomatic-hero-button"
									onClick={startGeneration}
									disabled={isRunning}
								>
									{isRunning ? __('Working…', 'mockomatic') : __('Start Generating', 'mockomatic')}
								</button>
								{isRunning && (
									<Button
										variant="secondary"
										onClick={isPaused ? resumeGeneration : pauseGeneration}
										disabled={!isRunning}
										className="mockomatic-stop-button"
									>
										{isPaused ? __('Resume', 'mockomatic') : __('Pause', 'mockomatic')}
									</Button>
								)}
							</div>
							<div className="mockomatic-hero-grid" aria-live="polite">
								<div className="mockomatic-hero-field">
									<div className="mockomatic-hero-field-title">
										<span className="dashicons dashicons-admin-post" aria-hidden="true" />
										<span>{__('Posts', 'mockomatic')}</span>
									</div>
									<TextControl
										label={__('Posts', 'mockomatic')}
										type="number"
										min={0}
										value={formState.posts}
										onChange={(value) => updateNumber('posts', value)}
										hideLabelFromVision
										help={__('Number of posts', 'mockomatic')}
									/>
								</div>
								<div className="mockomatic-hero-field">
									<div className="mockomatic-hero-field-title">
										<span className="dashicons dashicons-admin-page" aria-hidden="true" />
										<span>{__('Pages', 'mockomatic')}</span>
									</div>
									<TextControl
										label={__('Pages', 'mockomatic')}
										type="number"
										min={0}
										value={formState.pages}
										onChange={(value) => updateNumber('pages', value)}
										hideLabelFromVision
										help={__('Number of pages', 'mockomatic')}
									/>
								</div>
								<div className="mockomatic-hero-field">
									<div className="mockomatic-hero-field-title">
										<span className="dashicons dashicons-editor-textcolor" aria-hidden="true" />
										<span>{__('Text Generation', 'mockomatic')}</span>
									</div>
									<SelectControl
										label={__('Text model', 'mockomatic')}
										value={formState.textModel}
										options={textModelOptions}
										onChange={(value) => updateField('textModel', value)}
										hideLabelFromVision
										help={__('Model', 'mockomatic')}
									/>
								</div>
								<div className="mockomatic-hero-field">
									<div className="mockomatic-hero-field-title">
										<span className="dashicons dashicons-format-image" aria-hidden="true" />
										<span>{__('Image Generation', 'mockomatic')}</span>
									</div>
									<SelectControl
										label={__('Images', 'mockomatic')}
										value={formState.generateImages ? formState.imageModel : 'off'}
										onChange={handleImageModelChange}
										options={imageModelOptionsWithOff}
										hideLabelFromVision
										help={__('Select a model or disable images', 'mockomatic')}
									/>
								</div>
							</div>

							<Button
								variant="link"
								className="mockomatic-advanced-toggle"
								onClick={() => setShowForm((current) => !current)}
								aria-expanded={showForm}
							>
								{showForm
									? __('Hide advanced options ▲', 'mockomatic')
									: __('Show advanced options ▼', 'mockomatic')}
							</Button>
						</div>
						{errorMessage && (
							<Notice status="error" onRemove={() => setErrorMessage('')}>
								{errorMessage}
							</Notice>
						)}
					</CardBody>
				</Card>
			)}

			{showForm && !hasStarted && (
				<Card className="mockomatic-form-card">
					<CardBody>
						<form onSubmit={startGeneration}>
							<Panel>
								<PanelBody
									title={
										<span className="mockomatic-panel-title">
											<span className="dashicons dashicons-admin-post" aria-hidden="true" />
											{__('What to generate', 'mockomatic')}
										</span>
									}
									initialOpen
								>
									<Flex wrap>
										<FlexItem className="mockomatic-flex-field">
											<TextControl
												label={__('Generate posts', 'mockomatic')}
												type="number"
												min={0}
												value={formState.posts}
												onChange={(value) => updateNumber('posts', value)}
											/>
										</FlexItem>
										<FlexItem className="mockomatic-flex-field">
											<TextControl
												label={__('Generate pages', 'mockomatic')}
												type="number"
												min={0}
												value={formState.pages}
												onChange={(value) => updateNumber('pages', value)}
											/>
										</FlexItem>
									</Flex>
									<Flex wrap style={{ marginTop: '16px' }}>
										<FlexItem className="mockomatic-flex-field">
											<ToggleControl
												label={__('Create categories', 'mockomatic')}
												checked={formState.categories}
												onChange={(value) => updateField('categories', value)}
											/>
										</FlexItem>
										<FlexItem className="mockomatic-flex-field">
											<ToggleControl
												label={__('Create tags', 'mockomatic')}
												checked={formState.tags}
												onChange={(value) => updateField('tags', value)}
											/>
										</FlexItem>
										<FlexItem className="mockomatic-flex-field">
											<ToggleControl
												label={__('Generate featured images (Replicate)', 'mockomatic')}
												checked={formState.generateImages}
												onChange={(value) => updateField('generateImages', value)}
											/>
										</FlexItem>
									</Flex>
								</PanelBody>

								<PanelBody
									title={
										<span className="mockomatic-panel-title">
											<span className="dashicons dashicons-admin-settings" aria-hidden="true" />
											{__('Models', 'mockomatic')}
										</span>
									}
									initialOpen
								>
									<Flex wrap>
										<FlexItem className="mockomatic-flex-field">
											<SelectControl
												label={__('Text model', 'mockomatic')}
												value={formState.textModel}
												options={textModelOptions}
												onChange={(value) => updateField('textModel', value)}
											/>
										</FlexItem>
										<FlexItem className="mockomatic-flex-field">
											<SelectControl
												label={__('Image model (Replicate)', 'mockomatic')}
												value={formState.imageModel}
												options={imageModelOptions}
												onChange={(value) => updateField('imageModel', value)}
												help={__(
													'Used only when featured images are enabled.',
													'mockomatic'
												)}
												disabled={!formState.generateImages}
											/>
										</FlexItem>
									</Flex>
								</PanelBody>

								<PanelBody
									title={
										<span className="mockomatic-panel-title">
											<span className="dashicons dashicons-format-status" aria-hidden="true" />
											{__('Custom prompt', 'mockomatic')}
										</span>
									}
									initialOpen={false}
								>
									<TextareaControl
										label={__('Optional instructions', 'mockomatic')}
										value={formState.instructions}
										onChange={(value) => updateField('instructions', value)}
										rows={5}
										placeholder={__(
											'e.g. Tech blog in English, playful tone, posts around 1200 words, pages shorter and more formal…',
											'mockomatic'
										)}
									/>
								</PanelBody>
							</Panel>
							<div className="mockomatic-form-actions">
								<Button type="submit" variant="primary" disabled={isRunning}>
									{isRunning ? __('Working…', 'mockomatic') : __('Start generating', 'mockomatic')}
								</Button>
								<Button
									variant="tertiary"
									onClick={() => setShowForm(false)}
									disabled={isRunning}
									className="mockomatic-cancel-button"
								>
									{__('Close advanced options', 'mockomatic')}
								</Button>
							</div>
						</form>
					</CardBody>
				</Card>
			)}

			{(hasStarted || isRunning) && (
				<Card className="mockomatic-log-card">
					<CardBody>
						<div className="mockomatic-log-heading">
							<div className="mockomatic-heading-with-icon">
								<span className="dashicons dashicons-clock" aria-hidden="true" />
								<strong>{__('Generation status', 'mockomatic')}</strong>
							</div>
							{isRunning && (
								<Button
									variant="secondary"
									onClick={isPaused ? resumeGeneration : pauseGeneration}
									disabled={!isRunning}
									className="mockomatic-stop-button"
									size="small"
								>
									{isPaused ? __('Resume', 'mockomatic') : __('Pause', 'mockomatic')}
								</Button>
							)}
						</div>
						<div className="mockomatic-progress-box">
							<progress value={progress.value} max="100" aria-valuemin="0" aria-valuemax="100">
								{progress.value}%
							</progress>
						</div>

						<ul className="mockomatic-log" aria-live="polite">
							{logs.map((entry, index) => (
								<li key={`${entry.text}-${index}`} className={entry.isError ? 'mockomatic-log-error' : ''}>
									{entry.text}
								</li>
							))}
							{!logs.length && (
								<li className="mockomatic-log-empty">
									{__('No messages yet. Click “Start generating” to begin.', 'mockomatic')}
								</li>
							)}
						</ul>

						{tasks.length > 0 && (
							<div className="mockomatic-task-list" aria-live="polite">
								<div className="mockomatic-task-list-title">
									<span className="dashicons dashicons-list-view" aria-hidden="true" />
									{__('Queued items', 'mockomatic')}
								</div>
								<ul>
									{tasks.map((task, index) => {
										const viewUrl =
											siteBaseUrl && task.post_id
												? `${siteBaseUrl}/?${task.type === 'page' ? 'page_id' : 'p'}=${task.post_id}`
												: '';

										return (
											<li key={`${task.title}-${index}`} className={`status-${task.status}`}>
												<span className="mockomatic-task-status" aria-hidden="true" />
												<div className="mockomatic-task-text">
													<div className="mockomatic-task-title">
														<strong>{task.title}</strong>
														{task.post_id && adminEditBase && (
															<a
																href={`${adminEditBase}?post=${task.post_id}&action=edit`}
																className="mockomatic-task-link"
																target="_blank"
																rel="noreferrer"
															>
																<span className="dashicons dashicons-edit" aria-hidden="true" />
																{task.type === 'page'
																	? __('Edit Page', 'mockomatic')
																	: __('Edit Post', 'mockomatic')}
															</a>
														)}
														{viewUrl && (
															<a
																href={viewUrl}
																className="mockomatic-task-link"
																target="_blank"
																rel="noreferrer"
															>
																<span className="dashicons dashicons-visibility" aria-hidden="true" />
																{task.type === 'page'
																	? __('View Page', 'mockomatic')
																	: __('View Post', 'mockomatic')}
															</a>
														)}
													</div>
													<span className="mockomatic-task-meta">
														{task.type === 'post'
															? __('Post', 'mockomatic')
															: __('Page', 'mockomatic')}
													</span>
													{task.type === 'post' && (
														<span className="mockomatic-task-meta mockomatic-task-taxonomies">
															{task.categories?.length > 0 && (
																<span className="mockomatic-task-taxonomy">
																	<span className="dashicons dashicons-category" aria-hidden="true" />
																	{task.categories.join(', ')}
																</span>
															)}
															{task.tags?.length > 0 && (
																<span className="mockomatic-task-taxonomy">
																	<span className="dashicons dashicons-tag" aria-hidden="true" />
																	{task.tags.join(', ')}
																</span>
															)}
															{(!task.categories?.length && !task.tags?.length) && (
																<span className="mockomatic-task-taxonomy">
																	{__('No categories or tags', 'mockomatic')}
																</span>
															)}
														</span>
													)}
													{formState.generateImages && task.illustration_description && (
														<span className="mockomatic-task-meta mockomatic-task-prompt">
															{__('Illustration prompt:', 'mockomatic')}{' '}
															{task.illustration_description}
														</span>
													)}
													{task.note && <span className="mockomatic-task-note">{task.note}</span>}
												</div>
											</li>
										);
									})}
								</ul>
							</div>
						)}
					</CardBody>
				</Card>
			)}
		</div>
	);
};

document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('mockomatic-generate-root');

	if (root) {
		render(<GenerateContentApp />, root);
	}
});
