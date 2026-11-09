import React, { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import * as Dialog from '@radix-ui/react-dialog';
import * as Popover from '@radix-ui/react-popover';
import { motion, useReducedMotion } from 'framer-motion';
import Uppy from '@uppy/core';
import Dashboard from '@uppy/react/dashboard';
import DashboardModal from '@uppy/react/dashboard-modal';
import { Group as KonvaGroup, Image as KonvaImage, Layer as KonvaLayer, Line as KonvaLine, Rect as KonvaRect, Stage as KonvaStage, Text as KonvaText, Transformer as KonvaTransformer } from 'react-konva';
import Konva from 'konva';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { TextStyleKit } from '@tiptap/extension-text-style';
import TextAlign from '@tiptap/extension-text-align';
import {
    AlignCenter, AlignLeft, AlignRight, ArrowUpRight, Bold, Check, ChevronDown, ChevronRight, CircleHelp, FileImage,
    Flag, Heart, Heading2, ImagePlus, Italic, Lightbulb, List, ListOrdered, LoaderCircle, Lock,
    EyeOff, MessageCircle, MoreHorizontal, Pencil, Pin, Plus, Redo2, RotateCcw, Search, Send, ShieldCheck,
    Smile, Sparkles, Strikethrough, Trash2, Underline, Undo2, Users, Video, X,
} from 'lucide-react';
import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';
import '@uppy/react/css/style.css';
import '@fontsource-variable/plus-jakarta-sans';
import '@fontsource-variable/lora';
import '@fontsource-variable/manrope';
import '@fontsource-variable/dm-sans';
import '@fontsource-variable/fraunces';
import '@fontsource-variable/caveat';
import '@fontsource-variable/playfair-display';
import '../css/community-react.css';

type Reaction = 'like' | 'heart' | 'support' | 'heard' | 'helpful' | 'encouragement';
type Category = { key: string; label: string; icon: string };
type Comment = { id: number; content: string; created_at: string | null; author: { name: string; role_label: string | null }; can_edit: boolean; can_delete: boolean; replies?: Comment[] };
type RichMark = { type: 'bold' | 'italic' | 'strike' | 'code' } | { type: 'textStyle'; attrs?: { fontFamily?: string; fontSize?: string; color?: string } };
type RichNode = { type: string; text?: string; attrs?: { level?: number; textAlign?: 'left' | 'center' | 'right'; start?: number; type?: string }; marks?: RichMark[]; content?: RichNode[] };
type PostStyle = { background: 'canvas' | 'sage-grid' | 'sunrise-wash' | 'forest-dots' | 'lavender-wave'; font: 'jakarta' | 'lora' | 'manrope' | 'dm-sans' | 'fraunces' | 'caveat' | 'playfair-display'; size: 'compact' | 'standard' | 'display' };
type CanvasLayer = {
    id: string; type: 'image' | 'text' | 'sticker'; x: number; y: number; width: number; height: number; rotation: number; opacity: number;
    text?: string; font?: PostStyle['font']; font_size?: number; color?: string; bold?: boolean; italic?: boolean; align?: 'left' | 'center' | 'right'; background_color?: string | null; emoji?: string;
};
type VisualComposition = { version: 2; width: 1080; height: 1350; background: string; layers: CanvasLayer[] };
type Post = {
    id: number; title: string | null; message: string; category: string; category_label: string; category_icon: string;
    support_label: string | null; post_format: 'text' | 'rich' | 'visual'; image_url: string | null;
    rich_content: RichNode | null; post_style: PostStyle | null; visual_layout: VisualComposition | Record<string, unknown> | null;
    visual_text?: string | null; composition_image_url?: string | null; source_image_url?: string | null;
    resource: { id: number; title: string; description: string | null; category: string | null; url: string } | null;
    author: { name: string; initial: string; anonymous: boolean; role_label: string | null };
    created_at: string | null; is_pinned: boolean; replies_count: number; reaction_counts: Record<Reaction, number>;
    viewer_reaction: Reaction | null; is_owner: boolean; moderation_status: string | null; is_approved: boolean | null;
    can_moderate?: boolean; can_restore?: boolean;
};
type Feed = {
    viewer: { name: string; role: string; role_label: string | null; is_student: boolean; can_interact: boolean; is_admin?: boolean; can_moderate?: boolean };
    filters: { category: string; sort: string; q: string; campus: string | null; view: 'feed' | 'mine'; status: string; moderation?: 'all' | 'needs_review' | 'hidden' | 'published' };
    categories: Category[]; campuses: string[]; posts: Post[]; my_posts: Post[];
    pagination: { current_page: number; last_page: number; total: number; has_more: boolean };
    my_pagination: { current_page: number; last_page: number; total: number; has_more: boolean };
    stats: { total?: number; total_posts?: number; by_category?: Record<string, number> };
    announcements: Post[]; shareable_resources: { id: number; title: string; description: string | null; category: string | null }[];
    urls: { post: string; report: string; resource_base: string; image_base: string; reaction_base: string; comments_base: string; comment_base: string; feed: string; moderation_approve?: string; moderation_reject?: string; moderation_pin?: string; moderation_hide?: string; moderation_restore?: string };
};
type Query = Feed['filters'];

const reactionMeta: { key: Reaction; label: string; icon: React.ReactNode }[] = [
    { key: 'like', label: 'Like', icon: <Check size={14} /> },
    { key: 'heart', label: 'Heart', icon: <Heart size={14} /> },
    { key: 'support', label: 'Support', icon: <Sparkles size={14} /> },
    { key: 'heard', label: 'Heard', icon: <MessageCircle size={14} /> },
];
const reportReasons = ['harassment', 'discrimination', 'harmful', 'privacy', 'spam', 'other'];
const postStyles: PostStyle[] = [
    { background: 'canvas', font: 'jakarta', size: 'standard' },
    { background: 'sage-grid', font: 'jakarta', size: 'standard' },
    { background: 'sunrise-wash', font: 'lora', size: 'standard' },
    { background: 'forest-dots', font: 'jakarta', size: 'standard' },
    { background: 'lavender-wave', font: 'lora', size: 'standard' },
];
const fontFamilies: Record<PostStyle['font'], string> = {
    jakarta: 'Plus Jakarta Sans Variable', lora: 'Lora Variable', manrope: 'Manrope Variable',
    'dm-sans': 'DM Sans Variable', fraunces: 'Fraunces Variable', caveat: 'Caveat Variable',
    'playfair-display': 'Playfair Display Variable',
};
const fontFamilyIds = Object.fromEntries(Object.entries(fontFamilies).map(([key, value]) => [value, key])) as Record<string, PostStyle['font']>;
const textColors = ['#173e32', '#2f6b4f', '#805000', '#633f7a', '#4b5563', '#111827', '#ffffff'] as const;
const fontSizes = ['14px', '16px', '18px', '22px', '28px', '32px'] as const;
const backgroundThemes = [
    { key: 'mint', label: 'Mint', style: { background: '#dff1e4', color: '#103f32' } },
    { key: 'ivory', label: 'Ivory', style: { background: '#faf5e8', color: '#284335' } },
    { key: 'forest', label: 'Forest', style: { background: '#174836', color: '#fff' } },
    { key: 'night', label: 'Night', style: { background: '#172d39', color: '#fff' } },
    { key: 'sunrise', label: 'Sunrise', style: { background: '#f5d09a', color: '#55391e' } },
];

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
async function requestJson<T>(url: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    if (!['GET', 'HEAD'].includes((init.method ?? 'GET').toUpperCase())) headers.set('X-CSRF-TOKEN', csrfToken());
    const response = await fetch(url, { credentials: 'same-origin', ...init, headers });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const first = payload?.errors ? Object.values(payload.errors).flat().at(0) : null;
        throw new Error(typeof first === 'string' ? first : (payload?.message ?? 'The Community request could not be completed.'));
    }
    return payload as T;
}
function buildFeedUrl(base: string, query: Partial<Query>): string {
    const url = new URL(base, window.location.origin);
    Object.entries(query).forEach(([key, value]) => { if (value && value !== 'all' && value !== 'feed') url.searchParams.set(key, value); });
    return url.toString();
}
function relativeTime(value: string | null): string {
    if (!value) return 'Just now';
    const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000));
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}

function CommunityHeader({ feed, onCompose, onView }: { feed: Feed; onCompose: () => void; onView: (view: 'feed' | 'mine') => void }) {
    return <header className="mc-card mc-header">
        <div className="mc-header-row"><div><div className="mc-eyebrow">A shared space for the MinSU community</div><h1>Community Circle</h1><p>Share what is on your mind. Everyday posts appear in the feed without waiting for approval; safety holds are used only when a post needs a closer look.</p></div>
            <div className="mc-header-actions"><button className="mc-button-secondary" onClick={() => onView(feed.filters.view === 'feed' ? 'mine' : 'feed')}><Users size={15} />{feed.filters.view === 'feed' ? 'My posts' : 'All posts'}</button>{feed.viewer.can_interact && <button className="mc-button" onClick={onCompose}><Plus size={15} />Create post</button>}</div>
        </div>
    </header>;
}
function CommunitySidebar({ feed, onCompose, onView, onCategory }: { feed: Feed; onCompose: () => void; onView: (view: 'feed' | 'mine') => void; onCategory: (category: string) => void }) {
    const popular = Object.entries(feed.stats.by_category ?? {}).filter(([, count]) => count > 0).slice(0, 4);
    return <aside className="mc-card mc-rail" aria-label="Community navigation"><div className="mc-brand"><span className="mc-brand-mark">✦</span><div><strong>Circle</strong><small>MinSU community</small></div></div>
        <div className="mc-nav-label">Your space</div><nav className="mc-nav"><button aria-current={feed.filters.view === 'feed' ? 'page' : undefined} onClick={() => onView('feed')}><Users size={16} />Community feed</button><button aria-current={feed.filters.view === 'mine' ? 'page' : undefined} onClick={() => onView('mine')}><Pencil size={16} />My posts</button></nav>
        {feed.viewer.can_interact && <button className="mc-create" onClick={onCompose}>Create post <ArrowUpRight size={14} /></button>}
        {popular.length > 0 && <><div className="mc-nav-label">Active topics</div><nav className="mc-nav">{popular.map(([key, count]) => <button key={key} onClick={() => onCategory(key)}><Sparkles size={15} />{feed.categories.find(c => c.key === key)?.label ?? key}<span style={{ marginLeft: 'auto' }}>{count}</span></button>)}</nav></>}
        <p className="mc-rail-note"><strong>A space to feel heard.</strong><br />Share at your pace. Anonymous student authors remain private from other members.</p>
    </aside>;
}
function CommunityFilters({ feed, onQuery }: { feed: Feed; onQuery: (next: Partial<Query>) => void }) {
    const [search, setSearch] = useState(feed.filters.q);
    useEffect(() => setSearch(feed.filters.q), [feed.filters.q]);
    const submit = (event: FormEvent) => { event.preventDefault(); onQuery({ q: search, view: 'feed' }); };
    return <><form className="mc-card mc-toolbar" onSubmit={submit}><div className="mc-search"><Search /><input aria-label="Search discussions" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search discussions" /></div><select className="mc-select" aria-label="Sort community posts" value={feed.filters.sort} onChange={e => onQuery({ sort: e.target.value, view: 'feed' })}><option value="recent">Most recent</option><option value="popular">Most supported</option></select><select className="mc-select" aria-label="Filter by campus" value={feed.filters.campus ?? ''} onChange={e => onQuery({ campus: e.target.value || null, view: 'feed' })}><option value="">All campuses</option>{feed.campuses.map(c => <option key={c}>{c}</option>)}</select></form>
        <div className="mc-topic-row" aria-label="Filter discussions by topic"><button className="mc-chip" data-active={feed.filters.category === 'all'} onClick={() => onQuery({ category: 'all', view: 'feed' })}>All topics</button>{feed.categories.map(category => <button key={category.key} className="mc-chip" data-active={feed.filters.category === category.key} onClick={() => onQuery({ category: category.key, view: 'feed' })}>{category.icon} {category.label}</button>)}</div>
    </>;
}
function AdminFeedControls({ feed, onModeration }: { feed: Feed; onModeration: (moderation: NonNullable<Query['moderation']>) => void }) {
    if (!feed.viewer.can_moderate) return null;
    const choices: { value: NonNullable<Query['moderation']>; label: string }[] = [
        { value: 'all', label: 'All feed posts' },
        { value: 'needs_review', label: 'Needs review' },
        { value: 'hidden', label: 'Hidden posts' },
        { value: 'published', label: 'Published' },
    ];
    return <section className="mc-card mc-moderation-toolbar" aria-label="Administrator feed controls">
        <div><strong>Feed oversight</strong><span>Moderate posts here; regular sharing does not wait for approval.</span></div>
        <div className="mc-moderation-filters">{choices.map(choice => <button key={choice.value} type="button" className="mc-chip" data-active={(feed.filters.moderation ?? 'all') === choice.value} onClick={() => onModeration(choice.value)}>{choice.label}</button>)}</div>
    </section>;
}
function PostAuthor({ post }: { post: Post }) { return <><div className="mc-avatar" aria-hidden="true">{post.author.initial}</div><div className="mc-author"><div className="mc-author-row"><strong>{post.author.name}</strong>{post.author.role_label && <span className="mc-role"><ShieldCheck size={10} /> {post.author.role_label}</span>}{post.is_pinned && <span className="mc-role"><Pin size={10} /> Pinned</span>}{post.moderation_status && post.moderation_status !== 'approved' && <span className="mc-status">{post.moderation_status}</span>}</div><time dateTime={post.created_at ?? undefined}>{relativeTime(post.created_at)}</time></div></>; }
const canvasBackgrounds: Record<string, { first: string; second: string }> = {
    mint: { first: '#e0f0e4', second: '#f7fbf7' }, ivory: { first: '#f7f0df', second: '#fffaf0' },
    forest: { first: '#164936', second: '#286c50' }, night: { first: '#172d39', second: '#304c58' }, sunrise: { first: '#f2c77f', second: '#faebd2' },
};
function useStageImage(source: string | null) {
    const [image, setImage] = useState<HTMLImageElement | null>(null);
    useEffect(() => {
        setImage(null); if (!source) return;
        let active = true; const next = new Image(); next.onload = () => { if (active) setImage(next); }; next.onerror = () => { if (active) setImage(null); }; next.src = source;
        return () => { active = false; next.onload = null; next.onerror = null; };
    }, [source]);
    return image;
}
const twemojiAssetRoot = '/assets/emoji/twemoji/64/';
function twemojiAssetUrl(emoji: string): string | null {
    const codepoints = Array.from(emoji).map(character => character.codePointAt(0)?.toString(16));
    if (!codepoints.length || codepoints.length > 16 || codepoints.some(point => !point || !/^[a-f0-9]+$/.test(point))) return null;
    return `${twemojiAssetRoot}${codepoints.join('-')}.png`;
}
const twemojiImages = new Map<string, Promise<HTMLImageElement>>();
function loadTwemojiImage(emoji: string): Promise<HTMLImageElement> {
    const src = twemojiAssetUrl(emoji);
    if (!src) return Promise.reject(new Error('unsupported-sticker'));
    const cached = twemojiImages.get(src);
    if (cached) return cached;
    const image = new Image();
    image.decoding = 'async';
    const pending = new Promise<HTMLImageElement>((resolve, reject) => {
        image.onload = () => image.naturalWidth > 0 ? resolve(image) : reject(new Error('empty-sticker-asset'));
        image.onerror = () => reject(new Error('missing-sticker-asset'));
        image.src = src;
    }).catch(error => { twemojiImages.delete(src); throw error; });
    twemojiImages.set(src, pending);
    return pending;
}
function KonvaSticker({ layer, common }: { layer: CanvasLayer; common: Record<string, any> }) {
    const [image, setImage] = useState<HTMLImageElement | null>(null);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        let active = true;
        setImage(null);
        setFailed(false);
        void loadTwemojiImage(layer.emoji ?? '').then(asset => {
            if (active) setImage(asset);
        }).catch(() => {
            if (active) setFailed(true);
        });
        return () => { active = false; };
    }, [layer.emoji]);
    if (image) return <KonvaImage {...common} image={image} />;

    // Never leave an empty selectable node: legacy/unsupported sequences get a
    // plainly labeled fallback, while new stickers are only inserted after load.
    const label = failed ? 'Sticker unavailable' : 'Loading sticker…';
    return <KonvaGroup {...common} listening={false} draggable={false}>
        <KonvaRect x={0} y={0} width={layer.width} height={layer.height} fill="#f3f6f1" stroke="#9aab9e" strokeWidth={2} cornerRadius={12} />
        <KonvaText x={6} y={layer.height / 2 - 12} width={layer.width - 12} height={24} text={label} fontSize={18} fill="#536b5d" align="center" verticalAlign="middle" />
    </KonvaGroup>;
}
function CompositionCanvas({ layout, imageUrl, editable = false, selectedId = null, onSelect, onUpdate }: {
    layout: VisualComposition; imageUrl: string | null; editable?: boolean; selectedId?: string | null;
    onSelect?: (id: string | null) => void; onUpdate?: (id: string, patch: Partial<CanvasLayer>) => void;
}) {
    const host = useRef<HTMLDivElement>(null); const transformer = useRef<Konva.Transformer | null>(null); const nodes = useRef<Record<string, Konva.Node | null>>({});
    const [displayWidth, setDisplayWidth] = useState(480); const [guides, setGuides] = useState({ x: false, y: false }); const image = useStageImage(imageUrl);
    useEffect(() => {
        if (!host.current) return;
        const measure = () => setDisplayWidth(Math.max(220, Math.min(620, host.current?.clientWidth ?? 480)));
        measure(); const observer = new ResizeObserver(measure); observer.observe(host.current); return () => observer.disconnect();
    }, []);
    useEffect(() => {
        if (!editable || !transformer.current) return;
        const node = selectedId ? nodes.current[selectedId] : null;
        transformer.current.nodes(node ? [node] : []); transformer.current.getLayer()?.batchDraw();
    }, [editable, selectedId, layout.layers]);
    const scale = displayWidth / layout.width; const displayHeight = displayWidth * layout.height / layout.width;
    const colors = canvasBackgrounds[layout.background] ?? canvasBackgrounds.mint;
    const selectedKind = layout.layers.find(layer => layer.id === selectedId)?.type;
    const finishTransform = (layer: CanvasLayer, node: Konva.Node) => {
        const scaleX = node.scaleX(); const scaleY = node.scaleY();
        const patch: Partial<CanvasLayer> = { x: node.x(), y: node.y(), rotation: node.rotation() };
        if (layer.type === 'image') { patch.width = Math.max(32, layer.width * scaleX); patch.height = Math.max(32, layer.height * scaleY); }
        else if (layer.type === 'text') { patch.width = Math.max(60, layer.width * scaleX); patch.height = Math.max(32, layer.height * scaleY); patch.font_size = Math.max(18, Math.min(128, Math.round((layer.font_size ?? 36) * scaleY))); }
        else { patch.width = Math.max(32, layer.width * scaleX); patch.height = Math.max(32, layer.height * scaleY); }
        node.scaleX(1); node.scaleY(1); onUpdate?.(layer.id, patch);
    };
    const handleDragMove = (event: Konva.KonvaEventObject<DragEvent>, layer: CanvasLayer) => {
        const node = event.target; const xNear = Math.abs(node.x() + layer.width / 2 - layout.width / 2) < 16; const yNear = Math.abs(node.y() + layer.height / 2 - layout.height / 2) < 16;
        if (xNear) node.x((layout.width - layer.width) / 2); if (yNear) node.y((layout.height - layer.height) / 2); setGuides({ x: xNear, y: yNear });
    };
    const selectEvents = (layer: CanvasLayer) => ({
        onClick: (event: Konva.KonvaEventObject<MouseEvent>) => { event.cancelBubble = true; onSelect?.(layer.id); },
        onTap: (event: Konva.KonvaEventObject<TouchEvent>) => { event.cancelBubble = true; onSelect?.(layer.id); },
        onDragMove: (event: Konva.KonvaEventObject<DragEvent>) => handleDragMove(event, layer),
        onDragEnd: (event: Konva.KonvaEventObject<DragEvent>) => { const node = event.target; onUpdate?.(layer.id, { x: node.x(), y: node.y() }); setGuides({ x: false, y: false }); },
        onTransformEnd: (event: Konva.KonvaEventObject<Event>) => finishTransform(layer, event.target),
    });
    return <div ref={host} className={`mc-canvas-frame${editable ? ' is-editable' : ''}`} style={{ aspectRatio: '4 / 5' }}>
        <KonvaStage width={displayWidth} height={displayHeight} scaleX={scale} scaleY={scale} onMouseDown={event => { if (event.target === event.target.getStage()) onSelect?.(null); }} onTouchStart={event => { if (event.target === event.target.getStage()) onSelect?.(null); }}>
            <KonvaLayer>
                <KonvaRect x={0} y={0} width={layout.width} height={layout.height} fillLinearGradientStartPoint={{ x: 0, y: 0 }} fillLinearGradientEndPoint={{ x: layout.width, y: layout.height }} fillLinearGradientColorStops={[0, colors.first, 1, colors.second]} listening={false} />
                {layout.background === 'forest' && Array.from({ length: 36 }, (_, index) => <KonvaRect key={`dot-${index}`} x={36 + (index % 6) * 194} y={42 + Math.floor(index / 6) * 210} width={3} height={3} cornerRadius={2} fill="#e8bb45" opacity={0.42} listening={false} />)}
                {layout.layers.map(layer => {
                    const common = { key: layer.id, ref: (node: Konva.Node | null) => { nodes.current[layer.id] = node; }, x: layer.x, y: layer.y, width: layer.width, height: layer.height, rotation: layer.rotation, opacity: layer.opacity, draggable: editable, ...selectEvents(layer) };
                    if (layer.type === 'image') return image ? <KonvaImage {...common} image={image} listening={editable} /> : <KonvaRect {...common} fill="#dce9df" stroke="#98b3a1" strokeWidth={2} dash={[9, 7]} listening={editable} />;
                    if (layer.type === 'sticker') return <KonvaSticker key={layer.id} layer={layer} common={common} />;
                    const font = fontFamilies[layer.font ?? 'jakarta'];
                    return <KonvaGroup {...common}>
                        {layer.background_color && <KonvaRect x={-10} y={-8} width={layer.width + 20} height={layer.height + 16} fill={layer.background_color} cornerRadius={12} opacity={0.92} listening={false} />}
                        <KonvaText x={0} y={0} width={layer.width} height={layer.height} text={layer.text ?? ''} fontFamily={font} fontSize={layer.font_size ?? 36} fontStyle={`${layer.bold ? 'bold ' : ''}${layer.italic ? 'italic' : ''}`.trim() || 'normal'} fill={layer.color ?? '#173e32'} align={layer.align ?? 'left'} verticalAlign="middle" wrap="word" lineHeight={1.15} listening={editable} />
                    </KonvaGroup>;
                })}
                {editable && guides.x && <KonvaLine points={[layout.width / 2, 0, layout.width / 2, layout.height]} stroke="#e8bb45" strokeWidth={2} dash={[12, 8]} listening={false} />}
                {editable && guides.y && <KonvaLine points={[0, layout.height / 2, layout.width, layout.height / 2]} stroke="#e8bb45" strokeWidth={2} dash={[12, 8]} listening={false} />}
                {editable && <KonvaTransformer ref={transformer} rotateEnabled={true} rotateAnchorOffset={24} anchorCornerRadius={7} keepRatio={selectedKind === 'image' || selectedKind === 'sticker'} flipEnabled={false} anchorSize={12} anchorStroke="#174936" anchorFill="#e8f4eb" borderStroke="#438566" borderStrokeWidth={2} boundBoxFunc={(oldBox, nextBox) => nextBox.width < 32 || nextBox.height < 32 ? oldBox : nextBox} />}
            </KonvaLayer>
        </KonvaStage>
    </div>;
}
function PostMedia({ post }: { post: Post }) { const hasLayeredArt = post.post_format === 'visual' && post.visual_layout?.version === 2; return <>{post.image_url && !hasLayeredArt && <div className="mc-post-media"><img src={post.image_url} alt={post.post_format === 'visual' ? 'Community visual post' : 'Shared community photo'} loading="lazy" /></div>}{post.resource && <a className="mc-resource" href={post.resource.url}><strong>{post.resource.title} <ArrowUpRight size={13} /></strong><span>{post.resource.description || 'Open this approved wellness resource.'}</span></a>}</>; }
function ReportMenu({ type, targetId, feed, onDone }: { type: 'post' | 'comment'; targetId: number; feed: Feed; onDone: (message: string) => void }) {
    const [open, setOpen] = useState(false); const [busy, setBusy] = useState(false);
    const submit = async (reason: string) => { setBusy(true); try { await requestJson(feed.urls.report, { method: 'POST', body: JSON.stringify({ type, target_id: targetId, reason }), headers: { 'Content-Type': 'application/json' } }); setOpen(false); onDone('Your report was sent to authorized moderators.'); } catch (error) { onDone(error instanceof Error ? error.message : 'Unable to send the report.'); } finally { setBusy(false); } };
    return <Popover.Root open={open} onOpenChange={setOpen}><Popover.Trigger asChild><button className="mc-icon-button" aria-label={`Report ${type}`}><Flag size={15} /></button></Popover.Trigger><Popover.Portal><Popover.Content className="mc-menu" sideOffset={6}>{reportReasons.map(reason => <button key={reason} disabled={busy} onClick={() => submit(reason)}>{reason[0].toUpperCase() + reason.slice(1)}</button>)}</Popover.Content></Popover.Portal></Popover.Root>;
}
function PostReactions({ post, feed, onReload, onToast }: { post: Post; feed: Feed; onReload: () => void; onToast: (message: string) => void }) {
    const [busy, setBusy] = useState(false);
    const react = async (reaction: Reaction) => { if (!feed.viewer.can_interact || busy) return; setBusy(true); try { const base = `${feed.urls.reaction_base}/${post.id}/reaction`; if (post.viewer_reaction === reaction) await requestJson(base, { method: 'DELETE' }); else await requestJson(base, { method: 'POST', body: JSON.stringify({ reaction }), headers: { 'Content-Type': 'application/json' } }); onReload(); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to save your reaction.'); } finally { setBusy(false); } };
    return <div className="mc-actions" aria-label="Post actions">{reactionMeta.map(item => <button key={item.key} disabled={!feed.viewer.can_interact || busy} className="mc-reaction" data-selected={post.viewer_reaction === item.key} onClick={() => react(item.key)}>{item.icon}{item.label}{post.reaction_counts[item.key] ? ` ${post.reaction_counts[item.key]}` : ''}</button>)}<span style={{ flex: 1 }} />{feed.viewer.can_interact && <ReportMenu type="post" targetId={post.id} feed={feed} onDone={onToast} />}</div>;
}
function CommentThread({ comment, postId, feed, onReload, onToast, depth = 0 }: { comment: Comment; postId: number; feed: Feed; onReload: () => void; onToast: (message: string) => void; depth?: number }) {
    const [reply, setReply] = useState(false); const [edit, setEdit] = useState(false); const [text, setText] = useState(comment.content); const [busy, setBusy] = useState(false);
    const submit = async (event: FormEvent, mode: 'reply' | 'edit') => { event.preventDefault(); if (!text.trim()) return; setBusy(true); try { if (mode === 'reply') { const body = new FormData(); body.set('content', text.trim()); body.set('parent_id', String(comment.id)); await requestJson(`${feed.urls.comments_base}/${postId}/comment`, { method: 'POST', body }); } else { const body = new FormData(); body.set('content', text.trim()); body.set('_method', 'PUT'); await requestJson(`${feed.urls.comment_base}/${comment.id}`, { method: 'POST', body }); } setReply(false); setEdit(false); setText(mode === 'reply' ? '' : text); onReload(); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to save comment.'); } finally { setBusy(false); } };
    const remove = async () => { if (!confirm('Remove this comment?')) return; setBusy(true); try { const body = new FormData(); body.set('_method', 'DELETE'); await requestJson(`${feed.urls.comment_base}/${comment.id}`, { method: 'POST', body }); onReload(); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to remove comment.'); } finally { setBusy(false); } };
    return <div className="mc-comment" style={{ marginLeft: depth ? 16 : 0 }}><strong>{comment.author.name}{comment.author.role_label ? ` · ${comment.author.role_label}` : ''}</strong><p>{comment.content}</p><div className="mc-comment-actions">{depth === 0 && feed.viewer.can_interact && <button className="mc-text-button" onClick={() => { setText(''); setReply(!reply); }}>Reply</button>}{comment.can_edit && <button className="mc-text-button" onClick={() => { setText(comment.content); setEdit(!edit); }}>Edit</button>}{comment.can_delete && <button className="mc-text-button" disabled={busy} onClick={remove}>Remove</button>}{feed.viewer.can_interact && <ReportMenu type="comment" targetId={comment.id} feed={feed} onDone={onToast} />}</div>{(reply || edit) && <form className="mc-comments-form" onSubmit={e => submit(e, reply ? 'reply' : 'edit')}><input autoFocus value={text} maxLength={1000} onChange={e => setText(e.target.value)} placeholder={reply ? 'Write a respectful reply' : 'Edit comment'} /><button className="mc-button" disabled={busy}>{busy ? <LoaderCircle className="spin" size={14} /> : <Send size={14} />}{reply ? 'Reply' : 'Save'}</button></form>}{comment.replies?.map(child => <CommentThread key={child.id} comment={child} postId={postId} feed={feed} onReload={onReload} onToast={onToast} depth={1} />)}</div>;
}
function CommentsSection({ post, feed, onReload, onToast }: { post: Post; feed: Feed; onReload: () => void; onToast: (message: string) => void }) {
    const [open, setOpen] = useState(false); const [comments, setComments] = useState<Comment[]>([]); const [text, setText] = useState(''); const [busy, setBusy] = useState(false);
    const load = async () => { try { const data = await requestJson<{ comments: { data?: Comment[] } | Comment[] }>(`${feed.urls.comments_base}/${post.id}/comments`); setComments(Array.isArray(data.comments) ? data.comments : (data.comments.data ?? [])); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to load comments.'); } };
    const toggle = () => { const next = !open; setOpen(next); if (next) void load(); };
    const submit = async (event: FormEvent) => { event.preventDefault(); if (!text.trim() || busy) return; setBusy(true); try { const body = new FormData(); body.set('content', text.trim()); await requestJson(`${feed.urls.comments_base}/${post.id}/comment`, { method: 'POST', body }); setText(''); await load(); onReload(); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to add comment.'); } finally { setBusy(false); } };
    return <><button className="mc-reaction" onClick={toggle}><MessageCircle size={14} />{post.replies_count || ''} {open ? 'Hide comments' : 'Comments'}</button>{open && <section className="mc-comments" aria-label="Post comments">{comments.map(comment => <CommentThread key={comment.id} comment={comment} postId={post.id} feed={feed} onReload={() => { void load(); onReload(); }} onToast={onToast} />)}{comments.length === 0 && <p className="mc-muted">No comments yet. Keep replies respectful and privacy-aware.</p>}{feed.viewer.can_interact && <form className="mc-comments-form" onSubmit={submit}><input value={text} maxLength={1000} onChange={e => setText(e.target.value)} placeholder="Add a supportive comment" /><button className="mc-button" disabled={busy}><Send size={14} />Comment</button></form>}</section>}</>;
}
function RichPostBody({ document, background = 'canvas' }: { document: RichNode; background?: PostStyle['background'] }) {
    const render = (node: RichNode, key: string): React.ReactNode => {
        const children = node.content?.map((child, index) => render(child, `${key}-${index}`));
        if (node.type === 'text') {
            let content: React.ReactNode = node.text ?? '';
            for (const mark of node.marks ?? []) {
                if (mark.type === 'bold') content = <strong key={`${key}-bold`}>{content}</strong>;
                if (mark.type === 'italic') content = <em key={`${key}-italic`}>{content}</em>;
                if (mark.type === 'strike') content = <s key={`${key}-strike`}>{content}</s>;
                if (mark.type === 'code') content = <code key={`${key}-code`}>{content}</code>;
                if (mark.type === 'textStyle') {
                    const attrs = mark.attrs ?? {};
                    const family = attrs.fontFamily && fontFamilyIds[attrs.fontFamily] ? attrs.fontFamily : undefined;
                    const size = fontSizes.includes(attrs.fontSize as typeof fontSizes[number]) ? attrs.fontSize : undefined;
                    const requestedColor = (attrs.color ?? '').toLowerCase();
                    const color = textColors.includes(requestedColor as typeof textColors[number]) ? (requestedColor === '#ffffff' && background !== 'forest-dots' ? '#173e32' : requestedColor) : undefined;
                    content = <span key={`${key}-style`} style={{ ...(family ? { fontFamily: `"${family}", sans-serif` } : {}), ...(size ? { fontSize: size } : {}), ...(color ? { color } : {}) }}>{content}</span>;
                }
            }
            return <React.Fragment key={key}>{content}</React.Fragment>;
        }
        const align = ['left', 'center', 'right'].includes(node.attrs?.textAlign ?? '') ? node.attrs?.textAlign : undefined;
        if (node.type === 'paragraph') return <p key={key} style={align ? { textAlign: align } : undefined}>{children}</p>;
        if (node.type === 'heading') return node.attrs?.level === 3
            ? <h3 key={key} style={align ? { textAlign: align } : undefined}>{children}</h3>
            : <h2 key={key} style={align ? { textAlign: align } : undefined}>{children}</h2>;
        if (node.type === 'bulletList') return <ul key={key}>{children}</ul>;
        if (node.type === 'orderedList') {
            const start = Number.isInteger(node.attrs?.start) && (node.attrs?.start ?? 1) >= 1 && (node.attrs?.start ?? 1) <= 1000 ? node.attrs?.start : undefined;
            const listType = ['1', 'a', 'A', 'i', 'I'].includes(node.attrs?.type ?? '') ? node.attrs?.type as '1' | 'a' | 'A' | 'i' | 'I' : undefined;
            return <ol key={key} start={start} type={listType}>{children}</ol>;
        }
        if (node.type === 'listItem') return <li key={key}>{children}</li>;
        if (node.type === 'hardBreak') return <br key={key} />;
        return <React.Fragment key={key}>{children}</React.Fragment>;
    };
    return <>{render(document, 'doc')}</>;
}
function RichPostView({ document, style }: { document: RichNode; style: PostStyle }) {
    return <div className="mc-post-rich" data-background={style.background} data-font={style.font} data-size={style.size}><RichPostBody document={document} background={style.background} /></div>;
}

function CommunityPostCard({ post, feed, onReload, onEdit, onToast }: { post: Post; feed: Feed; onReload: () => void; onEdit: (post: Post) => void; onToast: (message: string) => void }) {
    const reduced = useReducedMotion(); const [busy, setBusy] = useState(false);
    const remove = async () => { if (!confirm('Remove this post? This cannot be undone.')) return; setBusy(true); try { const body = new FormData(); body.set('_method', 'DELETE'); await requestJson(`${feed.urls.image_base}/${post.id}`, { method: 'POST', body }); onReload(); } catch (error) { onToast(error instanceof Error ? error.message : 'Unable to remove post.'); } finally { setBusy(false); } };
    const moderate = async (action: 'approve' | 'reject' | 'pin' | 'hide' | 'restore') => {
        const base = feed.urls[`moderation_${action}` as keyof Feed['urls']];
        if (!base) return onToast('Administrator feed controls are unavailable. Refresh and try again.');
        let reason: string | undefined;
        if (action === 'hide' || action === 'reject') {
            reason = window.prompt(action === 'hide' ? 'Why are you removing this post from the feed?' : 'Why should this safety-held post stay out of the feed?')?.trim();
            if (!reason) return;
        }
        if ((action === 'hide' || action === 'reject') && !window.confirm(action === 'hide' ? 'Hide this post from the shared feed? It will remain in the admin Hidden posts filter and can be restored.' : 'Keep this safety-held post out of the shared feed?')) return;
        setBusy(true);
        try {
            const url = base.replace('{postId}', String(post.id));
            const payload: Record<string, unknown> = {};
            if (reason) payload.reason = reason;
            if (action === 'pin') payload.pinned = !post.is_pinned;
            const response = await requestJson<{ message?: string }>(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            onToast(response.message ?? 'Feed updated.');
            onReload();
        } catch (error) { onToast(error instanceof Error ? error.message : 'The moderation action could not be saved.'); }
        finally { setBusy(false); }
    };
    const style = post.post_style ?? { background: 'canvas', font: 'jakarta', size: 'standard' };
    const postContent = post.post_format === 'visual' && post.visual_layout?.version === 2
        ? <>
            <div className="mc-post-artwork"><CompositionCanvas layout={post.visual_layout as VisualComposition} imageUrl={post.composition_image_url ?? null} /><span className="sr-only">Visual community composition</span></div>
            {post.rich_content?.type === 'doc' && <div className="mc-art-caption"><RichPostView document={post.rich_content} style={defaultRichStyle} /></div>}
        </>
        : post.post_format === 'rich' && post.rich_content
            ? <RichPostView document={post.rich_content} style={style} />
            : <p>{post.message}</p>;
    return <motion.article layout={!reduced} initial={reduced ? false : { opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="mc-card mc-post" id={`community-post-${post.id}`}>
        <div className="mc-post-top"><PostAuthor post={post} /><Popover.Root><Popover.Trigger asChild><button className="mc-icon-button" aria-label="Post options"><MoreHorizontal size={17} /></button></Popover.Trigger><Popover.Portal><Popover.Content className="mc-menu" sideOffset={6}>{post.is_owner && <><button onClick={() => onEdit(post)}><Pencil size={13} /> Edit post</button><button disabled={busy} onClick={remove}><Trash2 size={13} /> Remove post</button></>}<button onClick={() => onToast('Use the report button below to notify authorized moderators.')}><CircleHelp size={13} /> Safety help</button></Popover.Content></Popover.Portal></Popover.Root></div>
        {feed.viewer.can_moderate && (post.moderation_status === 'pending' || post.moderation_status === 'flagged') && <div className="mc-moderation-notice"><ShieldCheck size={15} /><span><strong>{post.moderation_status === 'flagged' ? 'Safety hold' : 'Awaiting review'}</strong> · This is held for a specific review reason; ordinary posts publish immediately.</span></div>}
        <div className="mc-post-body">{post.title && <h2>{post.title}</h2>}{postContent}<div className="mc-post-tags"><span className="mc-tag">{post.category_icon} {post.category_label}</span>{post.support_label && <span className="mc-tag">{post.support_label}</span>}{post.post_format === 'visual' && <span className="mc-tag"><ImagePlus size={11} /> Visual post</span>}</div></div>
        {feed.viewer.can_moderate && <div className="mc-moderation-actions" aria-label={`Admin controls for post ${post.id}`}>
            {post.is_approved && <button type="button" disabled={busy} className="mc-text-button" onClick={() => void moderate('pin')}><Pin size={13} />{post.is_pinned ? 'Unpin' : 'Pin'}</button>}
            {post.is_approved && <button type="button" disabled={busy} className="mc-text-button mc-moderation-hide" onClick={() => void moderate('hide')}><EyeOff size={13} />Hide from feed</button>}
            {!post.is_approved && (post.moderation_status === 'pending' || post.moderation_status === 'flagged') && <><button type="button" disabled={busy} className="mc-button mc-moderation-approve" onClick={() => void moderate('approve')}><Check size={14} />Allow in feed</button><button type="button" disabled={busy} className="mc-text-button mc-moderation-hide" onClick={() => void moderate('reject')}><EyeOff size={13} />Keep hidden</button></>}
            {post.can_restore && <button type="button" disabled={busy} className="mc-button-secondary" onClick={() => void moderate('restore')}><RotateCcw size={14} />Restore to feed</button>}
        </div>}
        <PostMedia post={post} />{post.is_approved !== false && <><PostReactions post={post} feed={feed} onReload={onReload} onToast={onToast} /><CommentsSection post={post} feed={feed} onReload={onReload} onToast={onToast} /></>}
    </motion.article>;
}
function CommunityEmptyState({ canInteract, onCompose }: { canInteract: boolean; onCompose: () => void }) { return <section className="mc-card mc-empty"><Sparkles /><h2>A conversation can start gently.</h2><p>There are no published posts in this view yet. You can ask a question, share a small win, or offer encouragement.</p>{canInteract && <button className="mc-button" onClick={onCompose}><Plus size={15} />Start a conversation</button>}</section>; }
function CommunityFeed({ feed, onReload, onEdit, onCompose, onToast }: { feed: Feed; onReload: () => void; onEdit: (post: Post) => void; onCompose: () => void; onToast: (message: string) => void }) { const posts = feed.filters.view === 'mine' ? feed.my_posts : feed.posts; return <section className="mc-feed" aria-live="polite">{posts.length ? posts.map(post => <CommunityPostCard key={post.id} post={post} feed={feed} onReload={onReload} onEdit={onEdit} onToast={onToast} />) : <CommunityEmptyState canInteract={feed.viewer.can_interact} onCompose={onCompose} />}</section>; }

function FormattingToolbar({ editor }: { editor: ReturnType<typeof useEditor> }) {
    if (!editor) return null;
    const button = (label: string, active: boolean, action: () => void, icon: React.ReactNode, disabled = false) => <button type="button" data-active={active} aria-label={label} aria-pressed={active} title={label} disabled={disabled} onMouseDown={event => event.preventDefault()} onClick={action}>{icon}</button>;
    return <div className="mc-editor-toolbar" aria-label="Text formatting">
        {button('Bold', editor.isActive('bold'), () => editor.chain().focus().toggleBold().run(), <Bold size={15} />)}
        {button('Italic', editor.isActive('italic'), () => editor.chain().focus().toggleItalic().run(), <Italic size={15} />)}
        {button('Strikethrough', editor.isActive('strike'), () => editor.chain().focus().toggleStrike().run(), <Strikethrough size={15} />)}
        {button('Heading', editor.isActive('heading'), () => editor.chain().focus().toggleHeading({ level: 2 }).run(), <Heading2 size={15} />)}
        {button('Bulleted list', editor.isActive('bulletList'), () => editor.chain().focus().toggleBulletList().run(), <List size={15} />)}
        {button('Numbered list', editor.isActive('orderedList'), () => editor.chain().focus().toggleOrderedList().run(), <ListOrdered size={15} />)}
        <span className="mc-toolbar-spacer" />
        {button('Undo', false, () => editor.chain().focus().undo().run(), <Undo2 size={15} />, !editor.can().undo())}
        {button('Redo', false, () => editor.chain().focus().redo().run(), <Redo2 size={15} />, !editor.can().redo())}
        {button('Clear formatting', false, () => editor.chain().focus().unsetAllMarks().clearNodes().unsetTextAlign().run(), <span className="mc-clear-format">T×</span>)}
    </div>;
}
function RichTextEditor({ editor, showFormatting = true }: { editor: ReturnType<typeof useEditor>; showFormatting?: boolean }) { return <div className="mc-editor-shell">{showFormatting && <FormattingToolbar editor={editor} />}<EditorContent editor={editor} /></div>; }
function EmojiPicker({ onPick }: { onPick: (emoji: string) => void }) { const [open, setOpen] = useState(false); const emojis = ['🌱', '💛', '✨', '💬', '📚', '🫶', '☀️', '🌿']; return <Popover.Root open={open} onOpenChange={setOpen}><Popover.Trigger asChild><button type="button" className="mc-icon-button" aria-label="Insert emoji"><Smile size={16} /></button></Popover.Trigger><Popover.Portal><Popover.Content className="mc-menu" sideOffset={6}>{emojis.map(emoji => <button key={emoji} onClick={() => { onPick(emoji); setOpen(false); }}>{emoji}</button>)}</Popover.Content></Popover.Portal></Popover.Root>; }
function FontPicker({ font, onChange }: { font: string; onChange: (font: string) => void }) { const fonts = [{ key: 'Georgia, serif', label: 'Serif' }, { key: 'Inter, sans-serif', label: 'Modern' }, { key: 'cursive', label: 'Soft' }]; return <div className="mc-font-picker" aria-label="Preview typeface">{fonts.map(item => <button key={item.label} type="button" data-selected={font === item.key} style={{ fontFamily: item.key }} onClick={() => onChange(item.key)}>{item.label}</button>)}</div>; }
function BackgroundStudio({ theme, onTheme, text, font }: { theme: string; onTheme: (theme: string) => void; text: string; font: string }) { const selected = backgroundThemes.find(item => item.key === theme) ?? backgroundThemes[0]; return <div><div className="mc-preview" style={selected.style}><p style={{ fontFamily: font }}>{text || 'Your message preview'}</p></div><div className="mc-backgrounds" style={{ marginTop: 10 }}>{backgroundThemes.map(item => <button key={item.key} type="button" title={item.label} className="mc-background" data-selected={item.key === theme} style={item.style} onClick={() => onTheme(item.key)} />)}</div></div>; }
function plainText(node: RichNode): string {
    if (node.type === 'text') return node.text ?? '';
    if (node.type === 'hardBreak') return '\n';
    const content = (node.content ?? []).map(plainText).join('');
    return ['paragraph', 'heading', 'listItem'].includes(node.type) ? `${content}\n` : content;
}
function EmojiMartPicker({ onPick }: { onPick: (emoji: string) => void }) {
    const host = useRef<HTMLDivElement>(null);
    useEffect(() => {
        let active = true;
        void Promise.all([import('emoji-mart'), import('@emoji-mart/data')]).then(([{ Picker }, { default: data }]) => {
            if (!active || !host.current) return;
            const picker = new Picker({ data, set: 'native', theme: 'light', perLine: 8, searchPosition: 'sticky', previewPosition: 'none', navPosition: 'bottom', onEmojiSelect: (emoji: { native?: string }) => emoji.native && onPick(emoji.native) });
            host.current.replaceChildren(picker as unknown as Node);
        });
        return () => { active = false; host.current?.replaceChildren(); };
    }, [onPick]);
    return <div className="mc-emoji-mart" ref={host} aria-label="Search and insert an emoji" />;
}
function FontStudio({ style, onChange, editor }: { style: PostStyle; onChange: (next: PostStyle) => void; editor: ReturnType<typeof useEditor> }) {
    if (!editor) return null;
    const selectedFamily = fontFamilyIds[editor.getAttributes('textStyle').fontFamily] ?? style.font;
    const selectedSize = fontSizes.includes(editor.getAttributes('textStyle').fontSize as typeof fontSizes[number]) ? editor.getAttributes('textStyle').fontSize : '16px';
    const selectedColor = String(editor.getAttributes('textStyle').color ?? '').toLowerCase();
    const alignment = editor.getAttributes('paragraph').textAlign ?? editor.getAttributes('heading').textAlign ?? 'left';
    const alignButton = (value: 'left' | 'center' | 'right', icon: React.ReactNode, label: string) => <button type="button" className="mc-studio-icon" data-selected={alignment === value} aria-label={`Align ${label.toLowerCase()}`} aria-pressed={alignment === value} title={`Align ${label.toLowerCase()}`} onMouseDown={event => event.preventDefault()} onClick={() => editor.chain().focus().setTextAlign(value).run()}>{icon}</button>;
    return <section className="mc-font-studio" aria-label="Font and text styling">
        <div className="mc-studio-selects">
            <label className="mc-studio-control"><span>Font</span><select aria-label="Font family" value={selectedFamily} onChange={event => { const font = event.target.value as PostStyle['font']; editor.chain().focus().setFontFamily(fontFamilies[font]).run(); onChange({ ...style, font }); }} style={{ fontFamily: `"${fontFamilies[selectedFamily]}", sans-serif` }}>{Object.entries(fontFamilies).map(([key, family]) => <option key={key} value={key} style={{ fontFamily: `"${family}", sans-serif` }}>{family.replace(' Variable', '')}</option>)}</select></label>
            <label className="mc-studio-control mc-size-control"><span>Size</span><select aria-label="Font size" value={selectedSize} onChange={event => editor.chain().focus().setFontSize(event.target.value).run()}>{fontSizes.map(size => <option key={size} value={size}>{size.replace('px', '')} px</option>)}</select></label>
            <div className="mc-alignment-control" role="group" aria-label="Text alignment">{alignButton('left', <AlignLeft size={15} />, 'Left')}{alignButton('center', <AlignCenter size={15} />, 'Center')}{alignButton('right', <AlignRight size={15} />, 'Right')}</div>
        </div>
        <div className="mc-color-row"><span>Text color</span><div role="group" aria-label="Text color palette" className="mc-color-palette">{textColors.filter(color => color !== '#ffffff' || style.background === 'forest-dots').map(color => <button key={color} type="button" className="mc-color-swatch" style={{ backgroundColor: color }} data-selected={selectedColor === color} aria-label={`Text color ${color}`} aria-pressed={selectedColor === color} title={color} onMouseDown={event => event.preventDefault()} onClick={() => editor.chain().focus().setColor(color).run()} />)}</div></div>
    </section>;
}
function RichBackgroundStudio({ style, onChange, document, imageUrl }: { style: PostStyle; onChange: (next: PostStyle) => void; document: RichNode | null; imageUrl: string | null }) {
    const fallbackDocument = document?.type === 'doc' ? document : { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Your post preview' }] }] } as RichNode;
    const labels: Record<PostStyle['background'], string> = { canvas: 'Canvas', 'sage-grid': 'Sage grid', 'sunrise-wash': 'Sunrise', 'forest-dots': 'Forest dots', 'lavender-wave': 'Lavender wave' };
    return <div className="mc-preview-studio"><RichPostView document={fallbackDocument} style={style} />{imageUrl && <div className="mc-post-media mc-preview-media"><img src={imageUrl} alt="Selected post image preview" /></div>}<div className="mc-backgrounds" role="group" aria-label="Post background themes">{postStyles.map(item => <button key={item.background} type="button" className="mc-background-option" data-background={item.background} data-selected={item.background === style.background} aria-pressed={item.background === style.background} onClick={() => onChange({ ...style, background: item.background })}><span className="mc-background-swatch" /><span>{labels[item.background]}</span></button>)}</div></div>;
}
function MediaUploader({ uppy }: { uppy: Uppy<Record<string, unknown>, Record<string, unknown>> }) { return <div className="mc-uploader"><Dashboard uppy={uppy} hideUploadButton proudlyDisplayPoweredByUppy={false} height={175} note="JPEG, PNG, or WebP · up to 5 MB" /></div>; }
function MediaPreview({ file }: { file: File | null }) { return file ? <p className="mc-upload-note"><FileImage size={12} /> {file.name} is ready to be safely uploaded with this post.</p> : null; }
function PostAudienceSelector({ checked, onChange }: { checked: boolean; onChange: (checked: boolean) => void }) { return <label className="mc-anon"><input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} />Post as Anonymous Student. Your identity remains available only to authorized moderation.</label>; }
function PublishControls({ busy, editing, onCancel, onPublish }: { busy: boolean; editing: boolean; onCancel: () => void; onPublish: () => void }) { return <div className="mc-dialog-footer"><button className="mc-button-secondary" onClick={onCancel}>Cancel</button><button className="mc-button" disabled={busy} onClick={onPublish}>{busy ? <LoaderCircle className="spin" size={15} /> : <Send size={15} />}{editing ? 'Save changes' : 'Publish post'}</button></div>; }
function LegacyPostStudioDialog({ feed, open, onOpenChange, editing, onPublished, onToast }: { feed: Feed; open: boolean; onOpenChange: (open: boolean) => void; editing: Post | null; onPublished: () => void; onToast: (message: string) => void }) {
    const [mode, setMode] = useState<'text' | 'visual'>(editing?.post_format === 'visual' ? 'visual' : 'text'); const [category, setCategory] = useState(editing?.category ?? 'general'); const [anonymous, setAnonymous] = useState(false); const [theme, setTheme] = useState('mint'); const [font, setFont] = useState('Georgia, serif'); const [support, setSupport] = useState(''); const [resource, setResource] = useState(''); const [busy, setBusy] = useState(false); const [error, setError] = useState<string | null>(null);
    const [draft, setDraft] = useState(editing?.message ?? '');
    const editor = useEditor({ extensions: [StarterKit], content: editing?.message ?? '', onUpdate: ({ editor: currentEditor }) => setDraft(currentEditor.getText({ blockSeparator: '\n' })), editorProps: { attributes: { class: 'mc-editor-content', 'aria-label': 'Write your community post' } } });
    const uppy = useMemo(() => new Uppy<Record<string, unknown>, Record<string, unknown>>({ restrictions: { maxNumberOfFiles: 1, maxFileSize: 5 * 1024 * 1024, allowedFileTypes: ['image/jpeg', 'image/png', 'image/webp'] }, autoProceed: false }), []);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    useEffect(() => { const sync = () => setSelectedFile((uppy.getFiles()[0]?.data as File | undefined) ?? null); uppy.on('file-added', sync); uppy.on('file-removed', sync); return () => { uppy.off('file-added', sync); uppy.off('file-removed', sync); uppy.destroy(); }; }, [uppy]);
    useEffect(() => { if (!open) { uppy.cancelAll(); setSelectedFile(null); return; } setMode(editing?.post_format === 'visual' ? 'visual' : 'text'); setCategory(editing?.category ?? 'general'); setSupport(''); setResource(''); setAnonymous(false); setError(null); setDraft(editing?.message ?? ''); editor?.commands.setContent(editing?.message ?? ''); }, [open, editing, editor, uppy]);
    const publish = async () => { const text = editor?.getText({ blockSeparator: '\n' }).trim() ?? ''; if (text.length < 5) { setError('Write at least five characters before publishing.'); return; } setBusy(true); setError(null); try { const body = new FormData(); body.set('category', category); if (support) body.set('support_label', support); if (mode === 'visual') { body.set('post_format', 'visual'); body.set('visual_text', text.slice(0, 500)); body.set('visual_layout', JSON.stringify({ background: theme, font_size: 48, color: theme === 'forest' || theme === 'night' ? '#ffffff' : '#153f32', align: 'center', x: 50, y: 50 })); } else { body.set('message', text); if (resource) body.set('resource_id', resource); }
            if (feed.viewer.is_student && anonymous && !editing) body.set('is_anonymous', '1'); if (selectedFile) body.set('image', selectedFile);
            const url = editing ? `${feed.urls.image_base}/${editing.id}` : feed.urls.post; if (editing) body.set('_method', 'PUT'); await requestJson(url, { method: 'POST', body }); onOpenChange(false); onPublished(); onToast(mode === 'visual' ? 'Your visual post was sent for moderator review.' : 'Your post was submitted.');
        } catch (caught) { setError(caught instanceof Error ? caught.message : 'Unable to publish this post.'); } finally { setBusy(false); } };
    const text = draft.trim();
    return <Dialog.Root open={open} onOpenChange={onOpenChange}><Dialog.Portal><Dialog.Overlay className="mc-dialog-overlay" /><Dialog.Content className="mc-dialog" aria-describedby="post-studio-description"><div className="mc-dialog-header"><div><div className="mc-eyebrow">{editing ? 'Edit your post' : 'Unified post studio'}</div><Dialog.Title>{editing ? 'Update your post' : 'Share with the Circle'}</Dialog.Title><Dialog.Description id="post-studio-description">Text, a private photo upload, and safe visual styling belong to one post. Video upload is not available yet.</Dialog.Description></div><Dialog.Close asChild><button className="mc-icon-button" aria-label="Close post studio"><X size={18} /></button></Dialog.Close></div><div className="mc-dialog-body">{error && <div className="mc-error" role="alert">{error}</div>}<div className="mc-field"><label>Post format</label><div style={{ display: 'flex', gap: 8 }}><button type="button" disabled={Boolean(editing)} className={mode === 'text' ? 'mc-button' : 'mc-button-secondary'} onClick={() => setMode('text')}>Text & photo</button><button type="button" disabled={Boolean(editing)} className={mode === 'visual' ? 'mc-button' : 'mc-button-secondary'} onClick={() => setMode('visual')}>Visual post</button></div>{editing && <span className="mc-upload-note">Post type remains fixed while editing so the original moderation and media path stay consistent.</span>}</div><div className="mc-studio-grid"><div><div className="mc-field"><label>Topic</label><select className="mc-select" value={category} onChange={e => setCategory(e.target.value)}>{feed.categories.map(item => <option value={item.key} key={item.key}>{item.label}</option>)}</select></div><div className="mc-field"><label>Your message</label><RichTextEditor editor={editor} /></div><div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 14 }}><EmojiPicker onPick={emoji => editor?.chain().focus().insertContent(emoji).run()} /><span className="mc-muted">Formatting is sanitized to plain, safely-rendered community text when published.</span></div>{mode === 'text' && <><div className="mc-field"><label>Photo upload</label><MediaUploader uppy={uppy} /><MediaPreview file={selectedFile} /></div><p className="mc-upload-note"><Video size={12} /> Video posts are intentionally unavailable: no Laravel video-validation, storage, or playback path has been enabled.</p>{!editing && !feed.viewer.is_student && feed.shareable_resources.length > 0 && <div className="mc-field"><label>Approved wellness resource (optional)</label><select className="mc-select" value={resource} onChange={e => setResource(e.target.value)}><option value="">No resource attached</option>{feed.shareable_resources.map(item => <option key={item.id} value={item.id}>{item.title}</option>)}</select></div>}</>}</div><div><div className="mc-field"><label>{mode === 'visual' ? 'Visual preview' : 'Post preview'}</label>{mode === 'visual' ? <><BackgroundStudio theme={theme} onTheme={setTheme} text={text} font={font} /><div className="mc-field" style={{ marginTop: 14 }}><label>Preview typeface</label><FontPicker font={font} onChange={setFont} /><span className="mc-upload-note">The server uses the approved visual renderer; the typeface picker previews composition only.</span></div></> : <div className="mc-preview" style={{ background: '#f3f8f4', color: '#1f4534' }}><p style={{ fontFamily: font }}>{text || 'Your post preview'}</p></div>}</div><div className="mc-field"><label>What is this post for?</label><select className="mc-select" value={support} onChange={e => setSupport(e.target.value)}><option value="">No support label</option><option value="advice">Looking for advice</option><option value="encouragement">Need encouragement</option><option value="sharing">Just sharing</option><option value="listen">Someone to listen</option></select></div>{feed.viewer.is_student && !editing && <PostAudienceSelector checked={anonymous} onChange={setAnonymous} />}</div></div></div><PublishControls busy={busy} editing={Boolean(editing)} onCancel={() => onOpenChange(false)} onPublish={publish} /></Dialog.Content></Dialog.Portal></Dialog.Root>;
}

const defaultRichStyle: PostStyle = { background: 'canvas', font: 'jakarta', size: 'standard' };
function richDocumentFor(post: Post | null): RichNode {
    if (post?.rich_content?.type === 'doc') return post.rich_content;
    return { type: 'doc', content: [{ type: 'paragraph', content: post?.message ? [{ type: 'text', text: post.message }] : [] }] };
}
function blankComposition(background = 'mint'): VisualComposition { return { version: 2, width: 1080, height: 1350, background, layers: [] }; }
function emptyCaption(): RichNode { return { type: 'doc', content: [{ type: 'paragraph', content: [] }] }; }
function makePhotoLayer(width: number, height: number): CanvasLayer { const fit = Math.max(1080 / Math.max(1, width), 1350 / Math.max(1, height)); const w = width * fit; const h = height * fit; return { id: crypto.randomUUID(), type: 'image', x: (1080 - w) / 2, y: (1350 - h) / 2, width: w, height: h, rotation: 0, opacity: 1 }; }
function compositionFromPost(post: Post | null): VisualComposition {
    if (post?.visual_layout?.version === 2) return JSON.parse(JSON.stringify(post.visual_layout)) as VisualComposition;
    const prior = (post?.visual_layout && typeof post.visual_layout === 'object' ? post.visual_layout : {}) as Record<string, unknown>;
    const bg = ['mint', 'ivory', 'forest', 'night', 'sunrise'].includes(String(prior.background)) ? String(prior.background) : 'mint';
    const layers: CanvasLayer[] = [];
    if (post?.source_image_url) layers.push({ id: crypto.randomUUID(), type: 'image', x: 0, y: 0, width: 1080, height: 1350, rotation: 0, opacity: 1 });
    const text = String(post?.visual_text ?? post?.message ?? '').trim();
    if (text) layers.push({ id: crypto.randomUUID(), type: 'text', text, x: 115, y: 470, width: 850, height: 240, rotation: 0, opacity: 1, font: post?.post_style?.font ?? 'jakarta', font_size: Number(prior.font_size ?? 54), color: String(prior.color ?? (['forest', 'night'].includes(bg) ? '#ffffff' : '#173e32')), bold: false, italic: false, align: (prior.align as CanvasLayer['align']) ?? 'center', background_color: null });
    if (post?.post_format === 'rich' && post.rich_content) { const existingText = plainText(post.rich_content).trim(); if (existingText) layers.push({ id: crypto.randomUUID(), type: 'text', text: existingText.slice(0, 250), x: 100, y: 150, width: 880, height: 260, rotation: 0, opacity: 1, font: post.post_style?.font ?? 'jakarta', font_size: 42, color: '#173e32', bold: false, italic: false, align: 'left', background_color: null }); }
    return { version: 2, width: 1080, height: 1350, background: bg, layers };
}

function PostStudioDialog({ feed, open, onOpenChange, editing, onPublished, onToast }: { feed: Feed; open: boolean; onOpenChange: (open: boolean) => void; editing: Post | null; onPublished: () => void; onToast: (message: string) => void }) {
    const [scene, setScene] = useState<VisualComposition>(() => compositionFromPost(editing)); const sceneRef = useRef(scene); const undoStack = useRef<VisualComposition[]>([]); const redoStack = useRef<VisualComposition[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null); const [category, setCategory] = useState(editing?.category ?? 'general'); const [support, setSupport] = useState(editing?.support_label ?? ''); const [resource, setResource] = useState(String(editing?.resource?.id ?? ''));
    const [anonymous, setAnonymous] = useState(false); const [busy, setBusy] = useState(false); const [error, setError] = useState<string | null>(null); const [pickerOpen, setPickerOpen] = useState(false); const [emojiOpen, setEmojiOpen] = useState(false); const [preview, setPreview] = useState(false); const [zoom, setZoom] = useState(100);
    const [captionDoc, setCaptionDoc] = useState<RichNode>(emptyCaption()); const [selectedFile, setSelectedFile] = useState<File | null>(null); const [objectUrl, setObjectUrl] = useState<string | null>(null); const attachedFile = useRef<File | null>(null);
    const uppy = useMemo(() => new Uppy<Record<string, unknown>, Record<string, unknown>>({ restrictions: { maxNumberOfFiles: 1, maxFileSize: 5 * 1024 * 1024, allowedFileTypes: ['image/jpeg', 'image/png', 'image/webp'] }, autoProceed: false }), []);
    const imageUrl = objectUrl ?? editing?.source_image_url ?? editing?.composition_image_url ?? null; const photo = useStageImage(imageUrl);
    const captionEditor = useEditor({ extensions: [StarterKit.configure({ heading: { levels: [2, 3] } }), TextStyleKit.configure({ backgroundColor: false, lineHeight: false }), TextAlign.configure({ types: ['paragraph', 'heading'], alignments: ['left', 'center', 'right'], defaultAlignment: 'left' })], content: emptyCaption(), onUpdate: ({ editor: current }) => setCaptionDoc(current.getJSON() as RichNode), editorProps: { attributes: { id: 'community-post-caption', class: 'mc-editor-content', 'aria-label': 'Optional post caption' } } });
    const selected = scene.layers.find(layer => layer.id === selectedId) ?? null;
    const commit = useCallback((next: VisualComposition) => { undoStack.current = [...undoStack.current.slice(-39), sceneRef.current]; redoStack.current = []; sceneRef.current = next; setScene(next); }, []);
    const patchLayer = useCallback((id: string, patch: Partial<CanvasLayer>) => commit({ ...sceneRef.current, layers: sceneRef.current.layers.map(layer => layer.id === id ? { ...layer, ...patch } : layer) }), [commit]);
    useEffect(() => { const sync = () => setSelectedFile((uppy.getFiles()[0]?.data as File | undefined) ?? null); uppy.on('file-added', sync); uppy.on('file-removed', sync); return () => { uppy.off('file-added', sync); uppy.off('file-removed', sync); uppy.destroy(); }; }, [uppy]);
    useEffect(() => { if (!selectedFile) { setObjectUrl(null); attachedFile.current = null; return; } const next = URL.createObjectURL(selectedFile); setObjectUrl(next); return () => URL.revokeObjectURL(next); }, [selectedFile]);
    useEffect(() => { if (!selectedFile || !photo || attachedFile.current === selectedFile) return; const oldIndex = sceneRef.current.layers.findIndex(layer => layer.type === 'image'); const old = oldIndex >= 0 ? sceneRef.current.layers[oldIndex] : null; const image = old ? { ...old } : makePhotoLayer(photo.naturalWidth, photo.naturalHeight); const remaining = sceneRef.current.layers.filter(layer => layer.type !== 'image'); const layers = old ? [...remaining.slice(0, oldIndex), image, ...remaining.slice(oldIndex)] : [image, ...remaining]; commit({ ...sceneRef.current, layers }); setSelectedId(image.id); attachedFile.current = selectedFile; }, [selectedFile, photo, commit]);
    useEffect(() => { if (selectedFile || editing?.source_image_url || editing?.composition_image_url) return; const imageLayer = sceneRef.current.layers.find(layer => layer.type === 'image'); if (!imageLayer) return; const next = { ...sceneRef.current, layers: sceneRef.current.layers.filter(layer => layer.id !== imageLayer.id) }; commit(next); setSelectedId(next.layers.at(-1)?.id ?? null); }, [selectedFile, editing, commit]);
    useEffect(() => {
        if (!open) { uppy.cancelAll(); uppy.getFiles().forEach(file => uppy.removeFile(file.id)); setSelectedFile(null); return; }
        const next = compositionFromPost(editing); sceneRef.current = next; setScene(next); undoStack.current = []; redoStack.current = []; setSelectedId(next.layers.at(-1)?.id ?? null); setCategory(editing?.category ?? 'general'); setSupport(editing?.support_label ?? ''); setResource(String(editing?.resource?.id ?? '')); setAnonymous(false); setError(null); setPreview(false); setZoom(100);
        uppy.cancelAll(); uppy.getFiles().forEach(file => uppy.removeFile(file.id)); setSelectedFile(null); attachedFile.current = null;
        const caption = editing?.rich_content?.type === 'doc' ? editing.rich_content : (editing?.post_format === 'rich' ? richDocumentFor(editing) : emptyCaption()); setCaptionDoc(caption); captionEditor?.commands.setContent(caption);
    }, [open, editing, uppy]);
    const undo = () => { const prior = undoStack.current.pop(); if (!prior) return; redoStack.current.push(sceneRef.current); sceneRef.current = prior; setScene(prior); };
    const redo = () => { const next = redoStack.current.pop(); if (!next) return; undoStack.current.push(sceneRef.current); sceneRef.current = next; setScene(next); };
    const addText = () => { const layer: CanvasLayer = { id: crypto.randomUUID(), type: 'text', text: 'Tap to write something', x: 150, y: 500, width: 780, height: 220, rotation: 0, opacity: 1, font: 'jakarta', font_size: 56, color: ['forest', 'night'].includes(scene.background) ? '#ffffff' : '#173e32', bold: true, italic: false, align: 'center', background_color: null }; commit({ ...sceneRef.current, layers: [...sceneRef.current.layers, layer] }); setSelectedId(layer.id); };
    const addSticker = useCallback(async (emoji: string) => {
        setError(null);
        try { await loadTwemojiImage(emoji); }
        catch { setError('That emoji is not available as a local sticker yet. Choose another supported emoji.'); return; }
        const layer: CanvasLayer = { id: crypto.randomUUID(), type: 'sticker', emoji, x: 470, y: 200, width: 140, height: 140, rotation: 0, opacity: 1 };
        commit({ ...sceneRef.current, layers: [...sceneRef.current.layers, layer] }); setSelectedId(layer.id); setEmojiOpen(false); onToast('Color sticker added to the canvas.');
    }, [commit, onToast]);
    const deleteLayer = (id: string) => { const removed = sceneRef.current.layers.find(layer => layer.id === id); const next = { ...sceneRef.current, layers: sceneRef.current.layers.filter(layer => layer.id !== id) }; commit(next); setSelectedId(next.layers.at(-1)?.id ?? null); if (removed?.type === 'image' && selectedFile) { uppy.getFiles().forEach(file => uppy.removeFile(file.id)); setSelectedFile(null); attachedFile.current = null; } };
    const reorder = (id: string, direction: -1 | 1) => { const layers = [...sceneRef.current.layers]; const index = layers.findIndex(layer => layer.id === id); const other = index + direction; if (index < 0 || other < 0 || other >= layers.length) return; [layers[index], layers[other]] = [layers[other], layers[index]]; commit({ ...sceneRef.current, layers }); };
    const removePhoto = () => { uppy.getFiles().forEach(file => uppy.removeFile(file.id)); const next = { ...sceneRef.current, layers: sceneRef.current.layers.filter(layer => layer.type !== 'image') }; commit(next); setSelectedFile(null); attachedFile.current = null; setSelectedId(next.layers.at(-1)?.id ?? null); };
    const changeZoom = (value: number) => { const photoLayer = sceneRef.current.layers.find(layer => layer.id === selectedId && layer.type === 'image'); if (!photoLayer) { setZoom(value); return; } const ratio = value / Math.max(zoom, 1); const width = photoLayer.width * ratio; const height = photoLayer.height * ratio; const cx = photoLayer.x + photoLayer.width / 2; const cy = photoLayer.y + photoLayer.height / 2; patchLayer(photoLayer.id, { x: cx - width / 2, y: cy - height / 2, width, height }); setZoom(value); };
    const move = (id: string, patch: Partial<CanvasLayer>) => patchLayer(id, patch);
    const publish = async () => {
        if (!sceneRef.current.layers.length) { setError('Add a photo, text, or sticker to the canvas before publishing.'); return; }
        const caption = (captionEditor?.getJSON() as RichNode | undefined) ?? captionDoc; const captionText = plainText(caption).trim(); setBusy(true); setError(null);
        try { const current = sceneRef.current; const body = new FormData(); body.set('post_format', 'visual'); body.set('category', category); body.set('visual_text', current.layers.filter(layer => layer.type === 'text').map(layer => layer.text ?? '').join('\n').slice(0, 2000)); body.set('visual_layout', JSON.stringify(current));
            if (captionText) body.set('visual_caption', JSON.stringify(caption)); if (support) body.set('support_label', support); body.set('resource_id', resource); if (feed.viewer.is_student && anonymous && !editing) body.set('is_anonymous', '1'); if (selectedFile) body.set('image', selectedFile);
            const url = editing ? `${feed.urls.image_base}/${editing.id}` : feed.urls.post; if (editing) body.set('_method', 'PUT'); await requestJson(url, { method: 'POST', body }); onOpenChange(false); onPublished(); onToast(editing ? 'Your post was updated and returned for moderator review.' : 'Your composition was submitted for community review.');
        } catch (caught) { setError(caught instanceof Error ? caught.message : 'Unable to publish this composition.'); } finally { setBusy(false); }
    };
    return <Dialog.Root open={open} onOpenChange={onOpenChange}><Dialog.Portal><Dialog.Overlay className="mc-dialog-overlay" /><Dialog.Content className="mc-dialog mc-creative-dialog" aria-describedby="post-studio-description">
        <header className="mc-dialog-header mc-creative-header"><div><div className="mc-eyebrow">{editing ? 'Edit your composition' : 'Community Circle · Create post'}</div><Dialog.Title>{editing ? 'Update your post' : 'Create Post'}</Dialog.Title><Dialog.Description id="post-studio-description">Design directly on a private photo or a styled canvas. Posts appear right away unless community safeguards hold them for moderator review. Video and soundtrack tools are not enabled.</Dialog.Description></div><div className="mc-creative-identity"><span>{feed.viewer.name.slice(0, 1).toUpperCase()}</span><strong>{anonymous && !editing ? 'Anonymous Student' : feed.viewer.name}</strong><button className="mc-icon-button" type="button" aria-label="Close post studio" onClick={() => onOpenChange(false)}><X size={18} /></button></div></header>
        <div className="mc-dialog-body mc-creative-body">{error && <div role="alert" className="mc-error">{error}</div>}
            <div className="mc-creative-head-controls"><label className="mc-field mc-topic-field"><span>Topic</span><select className="mc-select" value={category} onChange={event => setCategory(event.target.value)}>{feed.categories.map(item => <option value={item.key} key={item.key}>{item.label}</option>)}</select></label><label className="mc-field mc-support-field"><span>Post intent</span><select className="mc-select" value={support} onChange={event => setSupport(event.target.value)}><option value="">No label</option><option value="advice">Looking for advice</option><option value="encouragement">Looking for encouragement</option><option value="sharing">Just sharing</option><option value="listen">Someone to listen</option></select></label></div>
            <div className="mc-creative-layout"><section className="mc-creative-workspace"><div className="mc-creative-toolbar" role="toolbar" aria-label="Visual composition tools"><button type="button" className="mc-tool-button" onClick={() => setPickerOpen(true)}><ImagePlus size={16} />Add Photo</button><button type="button" className="mc-tool-button" onClick={addText}><Plus size={16} />Add Text</button><Popover.Root open={emojiOpen} onOpenChange={setEmojiOpen}><Popover.Trigger asChild><button type="button" className="mc-tool-button" aria-label="Add emoji sticker" title="Add emoji sticker"><Smile size={16} />Emoji / Sticker</button></Popover.Trigger><Popover.Portal><Popover.Content className="mc-emoji-popover mc-emoji-mart-popover" side="bottom" align="start" sideOffset={7} collisionPadding={12}><EmojiMartPicker onPick={addSticker} /></Popover.Content></Popover.Portal></Popover.Root><button type="button" className="mc-tool-button" onClick={undo} disabled={!undoStack.current.length}><Undo2 size={15} />Undo</button><button type="button" className="mc-tool-button" onClick={redo} disabled={!redoStack.current.length}><Redo2 size={15} />Redo</button></div>
                <div className="mc-creative-canvas-wrap" role="group" aria-label="Editable post canvas. Use the layer list to select objects; arrow keys move the selected layer, Delete removes it, and Control or Command plus Z undoes." tabIndex={preview ? -1 : 0} onKeyDown={event => { if (event.target !== event.currentTarget) return; if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); event.shiftKey ? redo() : undo(); } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') { event.preventDefault(); redo(); } else if ((event.key === 'Delete' || event.key === 'Backspace') && selectedId) { event.preventDefault(); deleteLayer(selectedId); } else if (selected && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) { event.preventDefault(); const step = event.shiftKey ? 12 : 4; patchLayer(selected.id, { x: selected.x + (event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0), y: selected.y + (event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0) }); } }}><CompositionCanvas layout={scene} imageUrl={imageUrl} editable={!preview} selectedId={preview ? null : selectedId} onSelect={preview ? undefined : setSelectedId} onUpdate={preview ? undefined : move} />{scene.layers.length === 0 && <div className="mc-canvas-empty"><ImagePlus size={26} /><strong>Your post canvas</strong><span>Add a photo, text, or sticker to begin.</span></div>}{preview && <span className="mc-canvas-preview-label">Post preview</span>}</div>
                {!preview && selected?.type === 'image' && <div className="mc-crop-control"><label htmlFor="community-photo-zoom"><span>Crop and zoom</span><strong>{zoom}%</strong></label><input id="community-photo-zoom" type="range" min="70" max="220" step="5" value={zoom} onChange={event => changeZoom(Number(event.target.value))} /><small>Drag the photo to reposition it. Use canvas handles to resize or rotate.</small><button type="button" className="mc-text-button" onClick={removePhoto}>Remove photo</button></div>}
                {!preview && <div className="mc-background-studio"><div><strong>Canvas background</strong><span>Choose a calm starting point</span></div><div className="mc-background-options">{Object.entries(canvasBackgrounds).map(([key, item]) => <button key={key} type="button" aria-label={`${key} background`} aria-pressed={scene.background === key} data-selected={scene.background === key} onClick={() => commit({ ...sceneRef.current, background: key })}><span style={{ background: `linear-gradient(135deg,${item.first},${item.second})` }} />{key}</button>)}</div></div>}
                {!preview ? <div className="mc-caption-block"><strong>Optional caption</strong><span>Shown below the artwork in the feed.</span><div className="mc-caption-editor-wrap"><RichTextEditor editor={captionEditor} showFormatting /></div></div> : plainText(captionDoc).trim() ? <div className="mc-caption-preview"><RichPostView document={captionDoc} style={defaultRichStyle} /></div> : null}
            </section>
            {!preview && <aside className="mc-layer-panel"><div className="mc-layer-panel-heading"><div><strong>Design tools</strong><span>{selected ? `Selected: ${selected.type}` : 'Select a canvas layer'}</span></div>{selected && <button type="button" className="mc-icon-button" aria-label="Delete selected layer" onClick={() => deleteLayer(selected.id)}><Trash2 size={15} /></button>}</div>
                {selected?.type === 'text' && <div className="mc-layer-controls"><label className="mc-studio-control"><span>Text</span><textarea maxLength={250} value={selected.text ?? ''} onChange={event => patchLayer(selected.id, { text: event.target.value })} /></label><label className="mc-studio-control"><span>Font</span><select value={selected.font ?? 'jakarta'} onChange={event => patchLayer(selected.id, { font: event.target.value as PostStyle['font'] })}>{Object.entries(fontFamilies).map(([key, family]) => <option key={key} value={key}>{family.replace(' Variable', '')}</option>)}</select></label><label className="mc-studio-control"><span>Size · {selected.font_size ?? 36}px</span><input type="range" min="18" max="128" step="2" value={selected.font_size ?? 36} onChange={event => patchLayer(selected.id, { font_size: Number(event.target.value) })} /></label><div className="mc-layer-style-row"><label><input type="checkbox" checked={Boolean(selected.bold)} onChange={event => patchLayer(selected.id, { bold: event.target.checked })} /> Bold</label><label><input type="checkbox" checked={Boolean(selected.italic)} onChange={event => patchLayer(selected.id, { italic: event.target.checked })} /> Italic</label><select aria-label="Text alignment" value={selected.align ?? 'left'} onChange={event => patchLayer(selected.id, { align: event.target.value as CanvasLayer['align'] })}><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div><div className="mc-layer-color-row"><span>Text color</span>{textColors.map(color => <button key={color} type="button" className="mc-color-swatch" style={{ background: color }} aria-label={`Text color ${color}`} aria-pressed={selected.color === color} data-selected={selected.color === color} onClick={() => patchLayer(selected.id, { color })} />)}</div><label className="mc-studio-control"><span>Text background</span><select value={selected.background_color ?? ''} onChange={event => patchLayer(selected.id, { background_color: event.target.value || null })}><option value="">None</option><option value="#103f32">Forest</option><option value="#ffffff">White</option><option value="#e8bb45">Gold</option><option value="#172d39">Deep blue</option></select></label></div>}
                {selected?.type === 'sticker' && <div className="mc-layer-controls"><label className="mc-studio-control"><span>Opacity · {Math.round(selected.opacity * 100)}%</span><input type="range" min="10" max="100" value={Math.round(selected.opacity * 100)} onChange={event => patchLayer(selected.id, { opacity: Number(event.target.value) / 100 })} /></label></div>}
                {selected && <div className="mc-layer-order"><button type="button" className="mc-button-secondary" onClick={() => reorder(selected.id, -1)}>Send backward</button><button type="button" className="mc-button-secondary" onClick={() => reorder(selected.id, 1)}>Bring forward</button></div>}
                <div className="mc-layer-list-heading"><strong>Layers</strong><span>{scene.layers.length}/12</span></div><div className="mc-layer-list">{scene.layers.slice().reverse().map((layer, index) => <div className="mc-layer-item" key={layer.id} data-selected={selectedId === layer.id}><button type="button" onClick={() => setSelectedId(layer.id)}><span>{layer.type === 'image' ? <ImagePlus size={14} /> : layer.type === 'sticker' ? layer.emoji : <Pencil size={14} />}</span><span>{layer.type === 'text' ? (layer.text || 'Text layer').slice(0, 22) : layer.type === 'sticker' ? `${layer.emoji} Sticker` : 'Photo layer'}</span></button><span><button type="button" aria-label="Bring layer forward" disabled={index === 0} onClick={() => reorder(layer.id, 1)}>↑</button><button type="button" aria-label="Send layer backward" disabled={index === scene.layers.length - 1} onClick={() => reorder(layer.id, -1)}>↓</button></span></div>)}</div>
                {feed.viewer.is_student && !editing && <PostAudienceSelector checked={anonymous} onChange={setAnonymous} />}{!feed.viewer.is_student && feed.shareable_resources.length > 0 && <label className="mc-studio-control mc-resource-control"><span>Approved wellness resource</span><select className="mc-select" value={resource} onChange={event => setResource(event.target.value)}><option value="">None</option>{feed.shareable_resources.map(item => <option key={item.id} value={item.id}>{item.title}</option>)}</select></label>}
            </aside>}
            {preview && <div className="mc-preview-caption-note">The caption appears below the finished canvas in the published post.</div>}
            <DashboardModal uppy={uppy} open={pickerOpen} onRequestClose={() => setPickerOpen(false)} proudlyDisplayPoweredByUppy={false} hideUploadButton note="JPEG, PNG, or WebP · up to 5 MB. Upload happens with the post." />
        </div>
        </div>
        <footer className="mc-dialog-footer mc-creative-footer"><span className="mc-muted">Text posts are shared right away unless a specific safety check holds them. Photos and visual compositions may be held for review; video uploads are not active.</span><div><button className="mc-button-secondary" type="button" onClick={() => onOpenChange(false)}>Cancel</button><button className="mc-button-secondary" type="button" onClick={() => setPreview(value => !value)}>{preview ? 'Back to editor' : 'Preview'}</button><button className="mc-button" type="button" disabled={busy} onClick={publish}>{busy ? <LoaderCircle className="spin" size={15} /> : <Send size={15} />}{editing ? 'Save changes' : 'Publish'}</button></div></footer>
    </Dialog.Content></Dialog.Portal></Dialog.Root>;
}
function RichPostStudioLegacyDialog({ feed, open, onOpenChange, editing, onPublished, onToast }: { feed: Feed; open: boolean; onOpenChange: (open: boolean) => void; editing: Post | null; onPublished: () => void; onToast: (message: string) => void }) {
    const initialMode = editing?.post_format === 'visual' ? 'visual' : 'rich';
    const [mode, setMode] = useState<'rich' | 'visual'>(initialMode);
    const [category, setCategory] = useState(editing?.category ?? 'general');
    const [anonymous, setAnonymous] = useState(false);
    const [support, setSupport] = useState(editing?.support_label ?? '');
    const [resource, setResource] = useState('');
    const [style, setStyle] = useState<PostStyle>(editing?.post_style ?? defaultRichStyle);
    const [visualTheme, setVisualTheme] = useState('mint');
    const [draft, setDraft] = useState(editing?.message ?? '');
    const [richDocument, setRichDocument] = useState<RichNode>(() => richDocumentFor(editing));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] } }),
            TextStyleKit.configure({ backgroundColor: false, lineHeight: false }),
            TextAlign.configure({ types: ['paragraph', 'heading'], alignments: ['left', 'center', 'right'], defaultAlignment: 'left' }),
        ],
        content: richDocumentFor(editing),
        onUpdate: ({ editor: currentEditor }) => {
            setDraft(currentEditor.getText({ blockSeparator: '\n' }));
            setRichDocument(currentEditor.getJSON() as RichNode);
        },
        editorProps: { attributes: { id: 'community-post-editor', class: 'mc-editor-content', 'aria-label': 'Write your community post' } },
    });
    const uppy = useMemo(() => new Uppy<Record<string, unknown>, Record<string, unknown>>({ restrictions: { maxNumberOfFiles: 1, maxFileSize: 5 * 1024 * 1024, allowedFileTypes: ['image/jpeg', 'image/png', 'image/webp'] }, autoProceed: false }), []);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [selectedImageUrl, setSelectedImageUrl] = useState<string | null>(null);
    useEffect(() => { const sync = () => setSelectedFile((uppy.getFiles()[0]?.data as File | undefined) ?? null); uppy.on('file-added', sync); uppy.on('file-removed', sync); return () => { uppy.off('file-added', sync); uppy.off('file-removed', sync); uppy.destroy(); }; }, [uppy]);
    useEffect(() => { if (!selectedFile) { setSelectedImageUrl(null); return; } const url = URL.createObjectURL(selectedFile); setSelectedImageUrl(url); return () => URL.revokeObjectURL(url); }, [selectedFile]);
    useEffect(() => { if (!open) { uppy.cancelAll(); setSelectedFile(null); return; } setMode(editing?.post_format === 'visual' ? 'visual' : 'rich'); setCategory(editing?.category ?? 'general'); setSupport(editing?.support_label ?? ''); setResource(''); setAnonymous(false); setStyle(editing?.post_style ?? defaultRichStyle); setError(null); setDraft(editing?.message ?? ''); setRichDocument(richDocumentFor(editing)); editor?.commands.setContent(richDocumentFor(editing)); }, [open, editing, editor, uppy]);
    const insertEmoji = useCallback((emoji: string) => editor?.chain().focus().insertContent(emoji).run(), [editor]);
    const publish = async () => {
        const document = (editor?.getJSON() as RichNode | undefined) ?? richDocumentFor(editing);
        const text = plainText(document).trim();
        if (text.length < 5) { setError('Write at least five characters before publishing.'); return; }
        setBusy(true); setError(null);
        try {
            const body = new FormData(); body.set('category', category); if (support) body.set('support_label', support);
            if (mode === 'visual') { body.set('post_format', 'visual'); body.set('visual_text', text.slice(0, 500)); body.set('visual_layout', JSON.stringify({ background: visualTheme, font_size: 48, color: visualTheme === 'forest' || visualTheme === 'night' ? '#ffffff' : '#153f32', align: 'center', x: 50, y: 50 })); }
            else { body.set('post_format', 'rich'); body.set('rich_content', JSON.stringify(document)); body.set('post_style', JSON.stringify(style)); if (resource && !editing) body.set('resource_id', resource); }
            if (feed.viewer.is_student && anonymous && !editing) body.set('is_anonymous', '1'); if (selectedFile) body.set('image', selectedFile);
            const url = editing ? `${feed.urls.image_base}/${editing.id}` : feed.urls.post; if (editing) body.set('_method', 'PUT');
            await requestJson(url, { method: 'POST', body }); onOpenChange(false); onPublished(); onToast(mode === 'visual' ? 'Your visual post was sent for moderator review.' : 'Your rich post was submitted.');
        } catch (caught) { setError(caught instanceof Error ? caught.message : 'Unable to publish this post.'); } finally { setBusy(false); }
    };
    const document = (editor?.getJSON() as RichNode | undefined) ?? richDocument;
    return <Dialog.Root open={open} onOpenChange={onOpenChange}><Dialog.Portal><Dialog.Overlay className="mc-dialog-overlay" /><Dialog.Content className="mc-dialog" aria-describedby="post-studio-description">
        <div className="mc-dialog-header"><div><div className="mc-eyebrow">{editing ? 'Edit your post' : 'Community Circle'}</div><Dialog.Title>{editing ? 'Update your post' : 'Share with the Circle'}</Dialog.Title><Dialog.Description id="post-studio-description">Write, style, and share a thoughtful post. Photos are supported; video sharing isn’t available yet.</Dialog.Description></div><Dialog.Close asChild><button className="mc-icon-button" aria-label="Close post studio"><X size={18} /></button></Dialog.Close></div>
        <div className="mc-dialog-body">{error && <div className="mc-error" role="alert">{error}</div>}<div className="mc-studio-topline"><label className="mc-field mc-topic-field"><span>Topic</span><select className="mc-select" value={category} onChange={event => setCategory(event.target.value)}>{feed.categories.map(item => <option value={item.key} key={item.key}>{item.label}</option>)}</select></label><div className="mc-studio-mode" role="group" aria-label="Post type"><button type="button" disabled={Boolean(editing)} className={mode === 'rich' ? 'mc-button' : 'mc-button-secondary'} onClick={() => setMode('rich')}>Post</button><button type="button" disabled={Boolean(editing)} className={mode === 'visual' ? 'mc-button' : 'mc-button-secondary'} onClick={() => setMode('visual')}>Visual</button></div></div>
            <div className="mc-studio-main">
                <div className="mc-field"><label htmlFor="community-post-editor">Your message</label><RichTextEditor editor={editor} showFormatting={mode === 'rich'} /></div>
                {mode === 'rich' ? <div className="mc-studio-tools"><FontStudio style={style} onChange={setStyle} editor={editor} /></div> : <div className="mc-field"><label>Visual post preview</label><BackgroundStudio theme={visualTheme} onTheme={setVisualTheme} text={draft.trim()} font="Georgia, serif" /></div>}
                <div className="mc-emoji-row"><Popover.Root><Popover.Trigger asChild><button type="button" className="mc-icon-button" aria-label="Open searchable emoji picker" title="Add emoji" onMouseDown={event => event.preventDefault()}><Smile size={16} /></button></Popover.Trigger><Popover.Portal><Popover.Content className="mc-emoji-popover" side="bottom" align="start" sideOffset={8} collisionPadding={12} avoidCollisions><EmojiMartPicker onPick={insertEmoji} /></Popover.Content></Popover.Portal></Popover.Root><span>Add emoji</span></div>
                {mode === 'rich' && <details className="mc-live-preview"><summary>Preview and background</summary><RichBackgroundStudio style={style} onChange={setStyle} document={richDocument} imageUrl={selectedImageUrl ?? editing?.image_url ?? null} /></details>}
                <div className="mc-studio-options"><div className="mc-field mc-media-field"><label>Photo attachment</label><MediaUploader uppy={uppy} /><MediaPreview file={selectedFile} /><span className="mc-upload-note">Choose one photo, up to 5 MB.</span></div>{mode === 'rich' && !editing && !feed.viewer.is_student && feed.shareable_resources.length > 0 && <div className="mc-field"><label>Approved wellness resource (optional)</label><select className="mc-select" value={resource} onChange={event => setResource(event.target.value)}><option value="">No resource attached</option>{feed.shareable_resources.map(item => <option key={item.id} value={item.id}>{item.title}</option>)}</select></div>}<label className="mc-field mc-support-field"><span>Support label <small>(optional)</small></span><select className="mc-select" value={support} onChange={event => setSupport(event.target.value)}><option value="">None</option><option value="advice">Looking for advice</option><option value="encouragement">Need encouragement</option><option value="sharing">Just sharing</option><option value="listen">Someone to listen</option></select></label>{feed.viewer.is_student && !editing && <PostAudienceSelector checked={anonymous} onChange={setAnonymous} />}</div>
            </div></div>
        <PublishControls busy={busy} editing={Boolean(editing)} onCancel={() => onOpenChange(false)} onPublish={publish} />
    </Dialog.Content></Dialog.Portal></Dialog.Root>;
}

function CommunityPage({ initialUrl }: { initialUrl: string }) {
    const [feed, setFeed] = useState<Feed | null>(null); const [loading, setLoading] = useState(true); const [error, setError] = useState<string | null>(null); const [studio, setStudio] = useState(false); const [editing, setEditing] = useState<Post | null>(null); const [toast, setToast] = useState<string | null>(null); const latestUrl = useRef(initialUrl);
    const load = async (next?: Partial<Query>) => { setLoading(true); setError(null); try { const url = next && feed ? buildFeedUrl(feed.urls.feed, { ...feed.filters, ...next }) : latestUrl.current; latestUrl.current = url; const result = await requestJson<Feed>(url); setFeed(result); document.body.classList.add('community-react-ready'); } catch (caught) { setError(caught instanceof Error ? caught.message : 'The React Community interface could not load. The fallback page remains available.'); document.body.classList.remove('community-react-ready'); } finally { setLoading(false); } };
    useEffect(() => { void load(); return () => document.body.classList.remove('community-react-ready'); }, []);
    useEffect(() => { if (!toast) return; const timeout = window.setTimeout(() => setToast(null), 4500); return () => window.clearTimeout(timeout); }, [toast]);
    if (loading && !feed) return <div className="mc-react"><div className="mc-card mc-loading"><LoaderCircle className="spin" size={24} /> Loading Community Circle…</div></div>;
    if (!feed) return <div className="mc-react"><div className="mc-card mc-loading"><p>{error}</p><button className="mc-button-secondary" onClick={() => void load()}>Try React Community again</button></div></div>;
    const openStudio = () => { setEditing(null); setStudio(true); }; const chooseView = (view: 'feed' | 'mine') => void load({ view });
    return <div className="mc-react"><div className="mc-shell"><CommunitySidebar feed={feed} onCompose={openStudio} onView={chooseView} onCategory={category => void load({ category, view: 'feed' })} /><main className="mc-main"><CommunityHeader feed={feed} onCompose={openStudio} onView={chooseView} />{!feed.viewer.can_interact && <div className="mc-notice"><Lock size={14} /> Your current privacy consent allows reading moderated discussions but not posting or reacting.</div>}{feed.filters.view === 'feed' && <><CommunityFilters feed={feed} onQuery={next => void load(next)} /><AdminFeedControls feed={feed} onModeration={moderation => void load({ moderation, view: 'feed' })} /></>}{loading && <div className="mc-muted" style={{ padding: 8 }}>Refreshing feed…</div>}<CommunityFeed feed={feed} onReload={() => void load()} onCompose={openStudio} onEdit={post => { setEditing(post); setStudio(true); }} onToast={setToast} /></main><aside className="mc-side"><section className="mc-card mc-side-card"><div className="mc-eyebrow">Conversation starter</div><h2>A small start counts.</h2><p>What made this week a little better? Begin wherever feels comfortable.</p>{feed.viewer.can_interact && <button className="mc-button-secondary" style={{ marginTop: 14 }} onClick={openStudio}><Lightbulb size={14} />Share a thought</button>}</section><section className="mc-card mc-side-card"><div className="mc-eyebrow">Community care</div><h2>Respect is part of the space.</h2><p>Share experiences, protect privacy, and report harmful content. This is not an emergency service.</p><a className="mc-side-row" href="/student/resources">Explore wellness resources <ChevronRight size={14} /></a></section>{feed.announcements.length > 0 && <section className="mc-card mc-side-card"><div className="mc-eyebrow">Guidance announcements</div>{feed.announcements.map(item => <button className="mc-side-row" key={item.id} onClick={() => document.getElementById(`community-post-${item.id}`)?.scrollIntoView({ behavior: 'smooth' })}><span>{item.author.role_label ?? 'Guidance'}</span><span><ChevronRight size={14} /></span></button>)}</section>}</aside></div><PostStudioDialog feed={feed} open={studio} onOpenChange={setStudio} editing={editing} onPublished={() => void load({ view: editing ? 'mine' : 'feed' })} onToast={setToast} />{toast && <div className="mc-toast" role="status">{toast}</div>}</div>;
}

const mount = document.getElementById('community-react-root');
if (mount) createRoot(mount).render(<CommunityPage initialUrl={mount.dataset.feedUrl || '/student/community/data'} />);
