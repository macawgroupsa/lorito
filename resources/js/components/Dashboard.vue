<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    Activity, ArrowDownToLine, ArrowDownRight, ArrowUpRight, Bell, CalendarDays,
    Check, ChevronDown, ChevronRight, CircleDollarSign, ClipboardList, Clock3,
    Coins, Feather, LayoutDashboard, ListOrdered, LogOut, Menu, Minus, MoreHorizontal,
    Pencil, Plus, Search, Settings2, ShieldCheck, Sparkles, Store, Ticket, Trophy,
    Users, Wallet, X,
} from '@lucide/vue';

const user = ref(null);
const busy = ref(false);
const loading = ref(true);
const error = ref('');
const toast = ref('');
const module = ref('overview');
const showForm = ref(false);
const showMobileNav = ref(false);
const editingId = ref(null);
const search = ref('');
const selectedDate = ref(localDate());
const login = reactive({ email: 'admin@lorito.local', password: 'lorito2026', remember: true });
const data = reactive({ plays: [], points: [], lists: [], sales: [], results: [], users: [], totals: { sales: 0, pieces: 0, commission: 0, prizes: 0, net: 0 } });
const form = reactive({});
const salePointId = ref('');
const saleCustomer = ref('');
const saleAmount = ref('');
const selectedSalePlays = ref([]);
const selectedSaleNumbers = ref([]);
const saleCart = ref([]);
const issuedTicket = ref(null);
const showWinnerDialog = ref(false);
const winningPlay = ref(null);
const winningNumber = ref('');
const winnerError = ref('');
const saleNumbers = Array.from({ length: 100 }, (_, index) => String(index).padStart(2, '0'));
const sellerViews = [
    { value: 'sales', label: 'Ventas', description: 'Registrar ventas y consultar movimientos.' },
    { value: 'overview', label: 'Resumen', description: 'Ver los indicadores y resumen del punto.' },
    { value: 'plays', label: 'Jugadas', description: 'Consultar horarios y ventas por jugada.' },
    { value: 'results', label: 'Resultados', description: 'Consultar resultados y premios.' },
    { value: 'settlements', label: 'Cuadres', description: 'Ver el detalle de dinero y pedazos vendidos.' },
];
let refreshTimer;

function localDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function money(value) { return new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', minimumFractionDigits: 2 }).format(Number(value || 0)); }
function number(value) { return new Intl.NumberFormat('es-GT').format(Number(value || 0)); }
function time(value) {
    if (!value) return '—';
    const [hour, minute] = String(value).slice(0, 5).split(':').map(Number);
    const suffix = hour >= 12 ? 'p. m.' : 'a. m.';
    return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix}`;
}
function closeTime(play) {
    const [hours, minutes] = String(play.draw_time).slice(0, 5).split(':').map(Number);
    const total = (hours * 60 + minutes - Number(play.lock_minutes) + 1440) % 1440;
    return time(`${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`);
}
function flash(message) {
    toast.value = message;
    window.clearTimeout(flash.timer);
    flash.timer = window.setTimeout(() => toast.value = '', 3500);
}
async function api(url, method = 'GET', body = null) {
    const tenant = new URLSearchParams(window.location.search).get('tenant');
    if (tenant) url += `${url.includes('?') ? '&' : '?'}tenant=${encodeURIComponent(tenant)}`;
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    });
    if (response.status === 204) return null;
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
        throw new Error(validation || result.message || 'Ocurrió un error al guardar.');
    }
    return result;
}
async function loadData() {
    if (!user.value) { loading.value = false; return; }
    try {
        const result = await api(`/api/bootstrap?date=${selectedDate.value}`);
        user.value = result.user;
        if (result.user.role !== 'admin' && !result.user.permissions?.includes(module.value)) module.value = result.user.permissions?.[0] ?? 'sales';
        Object.assign(data, result);
        if (!salePointId.value && result.user.role === 'admin') salePointId.value = String(result.points.find(point => point.active)?.id ?? '');
        error.value = '';
    } catch (err) {
        if (err.message.includes('Unauthenticated') || err.message.includes('Unauthenticated.')) user.value = null;
        else error.value = err.message;
    } finally { loading.value = false; }
}
async function checkSession() {
    try {
        const result = await api(`/api/bootstrap?date=${selectedDate.value}`);
        user.value = result.user;
        if (result.user.role !== 'admin' && !result.user.permissions?.includes(module.value)) module.value = result.user.permissions?.[0] ?? 'sales';
        Object.assign(data, result);
        if (!salePointId.value && result.user.role === 'admin') salePointId.value = String(result.points.find(point => point.active)?.id ?? '');
        error.value = '';
        refreshTimer = window.setInterval(loadData, 15000);
    } catch { user.value = null; }
    finally { loading.value = false; }
}
async function signIn() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api('/login', 'POST', login);
        user.value = result.user;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && result.csrf_token) csrfMeta.content = result.csrf_token;
        await loadData();
        refreshTimer = window.setInterval(loadData, 15000);
    } catch (err) { error.value = err.message; }
    finally { busy.value = false; }
}
async function signOut() {
    await api('/api/logout', 'POST').catch(() => {});
    user.value = null;
    window.clearInterval(refreshTimer);
}

const admin = computed(() => user.value?.role === 'admin');
const sellerCount = computed(() => data.users.filter(item => item.role === 'seller').length);
const canCreate = computed(() => (module.value === 'sales' || (admin.value && !isReadOnly.value)) && !(module.value === 'users' && sellerCount.value >= 2));
const nav = computed(() => {
    let items = [
        { id: 'overview', label: 'Resumen', icon: LayoutDashboard, section: 'OPERACIÓN' },
        { id: 'plays', label: 'Jugadas', icon: Clock3, section: 'OPERACIÓN' },
        { id: 'sales', label: 'Ventas', icon: Ticket, section: 'OPERACIÓN' },
        { id: 'results', label: 'Resultados', icon: Trophy, section: 'OPERACIÓN' },
        { id: 'settlements', label: 'Cuadres', icon: CircleDollarSign, section: 'FINANZAS' },
    ];
    if (admin.value) items.splice(4, 0,
        { id: 'lists', label: 'Listas', icon: ListOrdered, section: 'ORGANIZACIÓN' },
        { id: 'points', label: 'Puntos de venta', icon: Store, section: 'ORGANIZACIÓN' },
        { id: 'users', label: 'Usuarios', icon: Users, section: 'ADMINISTRACIÓN' },
    );
    if (!admin.value) items = items.filter(item => (user.value?.permissions ?? ['sales']).includes(item.id));
    return items;
});
const groupedNav = computed(() => [...new Set(nav.value.map(item => item.section))].map(section => ({ section, items: nav.value.filter(item => item.section === section) })));
const currentLabel = computed(() => nav.value.find(item => item.id === module.value)?.label ?? 'Resumen');
const todayLabel = computed(() => new Intl.DateTimeFormat('es-GT', { dateStyle: 'full' }).format(new Date(`${selectedDate.value}T12:00:00`)));
const activePlays = computed(() => data.plays.filter(play => play.active && play.state === 'En venta'));
const chosenSalePoint = computed(() => Number(salePointId.value) || (admin.value ? null : user.value?.sales_point_id));
const salePointLists = computed(() => data.lists.filter(list => Number(list.sales_point_id) === Number(chosenSalePoint.value) && list.active && list.play?.active && list.play?.state === 'En venta'));
const salePlays = computed(() => activePlays.value.filter(play => salePointLists.value.some(list => Number(list.play_id) === Number(play.id))));
const saleLines = computed(() => saleCart.value.reduce((sum, item) => sum + Number(item.amount_quetzales), 0));
const selectedSaleLineCount = computed(() => selectedSalePlays.value.length * selectedSaleNumbers.value.length);
const dashboardCards = computed(() => [
    { label: 'Venta total', value: money(data.totals.sales), icon: Wallet, color: 'mint', note: `${number(data.totals.pieces)} pedazos registrados` },
    { label: 'Comisión de puntos', value: money(data.totals.commission), icon: Coins, color: 'sun', note: 'Calculada por lista y venta' },
    { label: 'Premios por pagar', value: money(data.totals.prizes), icon: Trophy, color: 'pink', note: data.results.length ? `${data.results.length} resultado(s) ingresado(s)` : 'A la espera de resultados' },
    { label: 'Neto de operación', value: money(data.totals.net), icon: CircleDollarSign, color: 'blue', note: 'Venta menos comisión y premios' },
]);

const configs = {
    plays: { title: 'Jugadas del día', desc: 'Horarios, cierres, premios y resultados.', action: 'Nueva jugada', endpoint: '/api/plays', fields: [
        { key: 'name', label: 'Nombre', required: true, placeholder: 'Ej. La Primera' },
        { key: 'draw_time', label: 'Hora del sorteo', type: 'time', required: true },
        { key: 'lock_minutes', label: 'Cerrar ventas antes (minutos)', type: 'number', required: true, min: 0 },
        { key: 'pieces_per_quetzal', label: 'Pedazos por quetzal', type: 'number', required: true, min: 1 },
        { key: 'prize_per_quetzal', label: 'Premio por quetzal (Q)', type: 'number', required: true, min: 0, step: '.01' },
        { key: 'active', label: 'Jugada activa', type: 'checkbox' },
    ] },
    points: { title: 'Puntos de venta', desc: 'Organiza los lugares y vendedores que trabajan tus listas.', action: 'Nuevo punto', endpoint: '/api/points', fields: [
        { key: 'name', label: 'Nombre del punto', required: true, placeholder: 'Ej. Punto Centro' },
        { key: 'contact_name', label: 'Persona encargada', placeholder: 'Nombre del contacto' },
        { key: 'phone', label: 'Teléfono', placeholder: 'Número de contacto' },
        { key: 'active', label: 'Punto activo', type: 'checkbox' },
    ] },
    lists: { title: 'Listas asignadas', desc: 'Asigna cada lista a un punto y define su comisión.', action: 'Asignar lista', endpoint: '/api/lists', fields: [
        { key: 'name', label: 'Nombre o código de lista', required: true, placeholder: 'Ej. Lista A-01' },
        { key: 'sales_point_id', label: 'Punto de venta', type: 'select', options: () => data.points.map(point => ({ value: point.id, label: point.name })), required: true },
        { key: 'play_id', label: 'Jugada', type: 'select', options: () => data.plays.map(play => ({ value: play.id, label: `${play.name} · ${time(play.draw_time)}` })), required: true },
        { key: 'commission_percentage', label: 'Comisión (%)', type: 'number', required: true, min: 0, max: 100, step: '.01' },
        { key: 'active', label: 'Lista activa', type: 'checkbox' },
    ] },
    sales: { title: 'Registro de ventas', desc: 'Anota el número elegido y el monto vendido por lista.', action: 'Registrar venta', endpoint: '/api/sales', fields: [
        { key: 'sales_list_id', label: 'Lista y jugada', type: 'select', options: () => data.lists.filter(list => list.active && !['Cerrada', 'Con resultado', 'Pendiente de resultado', 'Pausada'].includes(list.play?.state)).map(list => ({ value: list.id, label: `${list.name} · ${list.point?.name} · ${list.play?.name}` })), required: true },
        { key: 'number', label: 'Número jugado', required: true, inputmode: 'numeric', maxlength: 10, placeholder: 'Ej. 07' },
        { key: 'amount_quetzales', label: 'Monto en quetzales (Q)', type: 'number', required: true, min: '.01', step: '.01' },
    ] },
    results: { title: 'Resultados y premios', desc: 'Ingresa el número ganador para calcular automáticamente los premios.', action: 'Ingresar resultado', endpoint: '/api/results', fields: [
        { key: 'play_id', label: 'Jugada', type: 'select', options: () => data.plays.filter(play => play.state === 'Pendiente de resultado' || play.state === 'Con resultado').map(play => ({ value: play.id, label: `${play.name} · ${time(play.draw_time)}` })), required: true },
        { key: 'winning_number', label: 'Número ganador', required: true, inputmode: 'numeric', maxlength: 10, placeholder: 'Ej. 27' },
    ] },
    users: { title: 'Usuarios y accesos', desc: 'Tu cuenta es la única administradora. Puedes crear hasta 2 vendedores y elegir qué pantallas puede consultar cada uno.', action: 'Nuevo vendedor', endpoint: '/api/users', fields: [
        { key: 'name', label: 'Nombre completo', required: true },
        { key: 'email', label: 'Correo electrónico', type: 'email', required: true },
        { key: 'password', label: 'Contraseña (mínimo 8 caracteres)', type: 'password', required: true },
        { key: 'sales_point_id', label: 'Punto asignado', type: 'select', options: () => [{ value: '', label: 'Selecciona punto' }, ...data.points.map(point => ({ value: point.id, label: point.name }))], required: true },
        { key: 'permissions', label: 'Pantallas que puede ver el vendedor', type: 'permissions', options: sellerViews },
    ] },
};

const fields = computed(() => configs[module.value]?.fields ?? []);
const moduleTitle = computed(() => configs[module.value]?.title ?? '');
const moduleDesc = computed(() => configs[module.value]?.desc ?? '');
const moduleAction = computed(() => configs[module.value]?.action ?? '');
const headers = computed(() => ({
    plays: ['Jugada', 'Sorteo', 'Cierre', 'Venta', 'Pedazos', 'Comisión', 'Resultado', 'Estado'],
    points: ['Punto', 'Encargado', 'Teléfono', 'Listas', 'Estado'],
    lists: ['Lista', 'Punto', 'Jugada', 'Comisión', 'Ventas hoy', 'Estado'],
    sales: ['Número', 'Jugada', 'Lista / punto', 'Monto', 'Pedazos', 'Hora'],
    results: ['Jugada', 'Hora', 'Número ganador', 'Ventas acertadas', 'Premio por pagar', 'Estado'],
    users: ['Usuario', 'Correo', 'Rol', 'Punto asignado', 'Vistas permitidas'],
}[module.value] ?? []));

const rows = computed(() => {
    if (module.value === 'plays') return data.plays.map(play => ({ id: play.id, values: [play.name, time(play.draw_time), `-${play.lock_minutes} min`, money(play.sold), number(play.pieces), money(play.commission), play.winning_number ?? '—', play.state], record: play }));
    if (module.value === 'points') return data.points.map(point => ({ id: point.id, values: [point.name, point.contact_name || '—', point.phone || '—', number(point.lists_count), point.active ? 'Activo' : 'Pausado'], record: point }));
    if (module.value === 'lists') return data.lists.map(list => ({ id: list.id, values: [list.name, list.point?.name ?? '—', list.play?.name ?? '—', `${Number(list.commission_percentage).toFixed(2)}%`, money(listSalesAmount(list.id)), list.active ? 'Activa' : 'Pausada'], record: list }));
    if (module.value === 'sales') return data.sales.map(sale => ({ id: sale.id, values: [sale.number, sale.play?.name ?? '—', `${sale.list?.name ?? '—'} · ${sale.list?.point?.name ?? '—'}`, money(sale.amount_quetzales), number(Number(sale.amount_quetzales) * sale.pieces_per_quetzal), new Intl.DateTimeFormat('es-GT', { hour: 'numeric', minute: '2-digit' }).format(new Date(sale.sold_at))], record: sale }));
    if (module.value === 'results') return data.plays.map(play => ({ id: play.id, values: [play.name, time(play.draw_time), play.winning_number ?? 'Sin ingresar', play.winning_number ? money(play.matched_amount) : '—', play.winning_number ? money(play.prizes) : '—', play.state], record: play }));
    if (module.value === 'users') return data.users.map(item => ({ id: item.id, values: [item.name, item.email, item.role === 'admin' ? 'Administrador · Propietario' : 'Vendedor', item.sales_point?.name ?? '—', item.role === 'admin' ? 'Acceso completo' : (item.permissions ?? ['sales']).map(key => sellerViews.find(view => view.value === key)?.label ?? key).join(', ')], record: item }));
    return [];
});
const filteredRows = computed(() => rows.value.filter(row => row.values.join(' ').toLowerCase().includes(search.value.toLowerCase())));
const isReadOnly = computed(() => module.value === 'results');

function startModule(id) {
    if (!nav.value.some(item => item.id === id)) return;
    module.value = id; search.value = ''; showForm.value = false; showMobileNav.value = false; error.value = '';
}
function toggleChoice(collection, value) {
    const next = new Set(collection.value);
    next.has(value) ? next.delete(value) : next.add(value);
    collection.value = [...next];
}
function toggleSalePlay(playId) { toggleChoice(selectedSalePlays, playId); }
function toggleSaleNumber(value) { toggleChoice(selectedSaleNumbers, value); }
function resetSaleSelection() { selectedSalePlays.value = []; selectedSaleNumbers.value = []; saleAmount.value = ''; }
function addSaleSelection() {
    if (!chosenSalePoint.value) return flash('Selecciona el punto de venta.');
    if (!selectedSalePlays.value.length || !selectedSaleNumbers.value.length) return flash('Selecciona una jugada y por lo menos un número.');
    const amount = Number(saleAmount.value);
    if (!Number.isFinite(amount) || amount <= 0) return flash('Escribe cuánto jugará el cliente en cada número.');
    const additions = [];
    for (const playId of selectedSalePlays.value) {
        const list = salePointLists.value.find(row => Number(row.play_id) === Number(playId));
        if (!list) continue;
        for (const numberValue of selectedSaleNumbers.value) additions.push({ sales_list_id: list.id, play_id: playId, play_name: list.play.name, list_name: list.name, number: numberValue, amount_quetzales: amount });
    }
    const existing = new Set(saleCart.value.map(item => `${item.sales_list_id}-${item.number}`));
    const duplicates = additions.filter(item => existing.has(`${item.sales_list_id}-${item.number}`));
    if (duplicates.length) return flash('Ya agregaste uno o más números para esa jugada. Quita los repetidos del comprobante primero.');
    saleCart.value.push(...additions);
    resetSaleSelection();
    flash(`${additions.length} número(s) agregados al comprobante.`);
}
async function issueSaleTicket() {
    if (!saleCart.value.length) return flash('Agrega números antes de emitir el comprobante.');
    busy.value = true; error.value = '';
    try {
        issuedTicket.value = await api('/api/tickets', 'POST', {
            sales_point_id: chosenSalePoint.value,
            customer_name: saleCustomer.value || null,
            items: saleCart.value.map(({ sales_list_id, number, amount_quetzales }) => ({ sales_list_id, number, amount_quetzales })),
        });
        saleCart.value = []; saleCustomer.value = '';
        await loadData();
        flash('Venta guardada. El comprobante PDF está listo.');
    } catch (err) { error.value = err.message; }
    finally { busy.value = false; }
}
function openReceipt() { if (issuedTicket.value?.receipt_url) window.open(issuedTicket.value.receipt_url, '_blank', 'noopener'); }
function receiptUrl(sale) {
    if (!sale.sale_ticket_id) return null;
    const tenant = new URLSearchParams(window.location.search).get('tenant');
    return `/api/tickets/${sale.sale_ticket_id}/receipt${tenant ? `?tenant=${encodeURIComponent(tenant)}` : ''}`;
}
watch(chosenSalePoint, (next, previous) => {
    if (previous && next && Number(next) !== Number(previous)) {
        saleCart.value = [];
        resetSaleSelection();
        issuedTicket.value = null;
    }
});
function defaultsFor(id) {
    if (id === 'plays') return { name: '', draw_time: '10:00', lock_minutes: 10, pieces_per_quetzal: 80, prize_per_quetzal: 80, active: true };
    if (id === 'points') return { name: '', contact_name: '', phone: '', active: true };
    if (id === 'lists') return { name: '', sales_point_id: data.points[0]?.id ?? '', play_id: data.plays[0]?.id ?? '', commission_percentage: 5, active: true };
    if (id === 'sales') return { sales_list_id: data.lists[0]?.id ?? '', number: '', amount_quetzales: '' };
    if (id === 'results') return { play_id: data.plays.find(play => play.state === 'Pendiente de resultado')?.id ?? '', winning_number: '' };
    if (id === 'users') return { name: '', email: '', password: '', sales_point_id: data.points[0]?.id ?? '', permissions: ['sales'] };
    return {};
}
function openCreate() {
    if (!canCreate.value) return;
    if (module.value === 'sales' && !data.lists.length) return flash('Primero crea un punto y asigna una lista.');
    if (module.value === 'lists' && (!data.plays.length || !data.points.length)) return flash('Primero crea una jugada y un punto de venta.');
    if (module.value === 'users' && !admin.value) return;
    Object.assign(form, defaultsFor(module.value));
    editingId.value = null;
    showForm.value = true;
}
function editRow(row) {
    if (module.value === 'sales' || module.value === 'results') return;
    const record = row.record;
    const fieldsCopy = defaultsFor(module.value);
    if (module.value === 'lists') Object.assign(fieldsCopy, { ...record, sales_point_id: record.sales_point_id, play_id: record.play_id });
    else if (module.value === 'users') Object.assign(fieldsCopy, { ...record, password: '', permissions: record.permissions?.length ? [...record.permissions] : ['sales'] });
    else Object.assign(fieldsCopy, record);
    Object.assign(form, fieldsCopy);
    editingId.value = row.id;
    showForm.value = true;
}
async function saveForm() {
    busy.value = true;
    error.value = '';
    const config = configs[module.value];
    const payload = { ...form };
    for (const field of fields.value) if (field.type === 'number' && payload[field.key] !== '') payload[field.key] = Number(payload[field.key]);
    if (module.value === 'users' && !payload.password && editingId.value) delete payload.password;
    try {
        const method = editingId.value ? 'PUT' : 'POST';
        const url = editingId.value ? `${config.endpoint}/${editingId.value}` : config.endpoint;
        await api(url, method, payload);
        showForm.value = false;
        flash(editingId.value ? 'Cambios guardados.' : `${config.action.replace(/s$/, '')} creado correctamente.`);
        await loadData();
    } catch (err) { error.value = err.message; }
    finally { busy.value = false; }
}
async function removeRow(row) {
    if (!window.confirm(`¿Eliminar ${row.values[0]}?`)) return;
    const endpoint = configs[module.value].endpoint;
    try {
        await api(`${endpoint}/${row.id}`, 'DELETE');
        flash('Registro eliminado.');
        await loadData();
    } catch (err) { flash(err.message); }
}
async function saveResult(play) {
    winningPlay.value = play;
    winningNumber.value = play.winning_number ?? '';
    winnerError.value = '';
    showWinnerDialog.value = true;
}
async function submitWinner() {
    if (!winningPlay.value || !/^\d{1,2}$/.test(String(winningNumber.value))) {
        winnerError.value = 'Escribe un número entre 00 y 99.';
        return;
    }
    busy.value = true;
    winnerError.value = '';
    try {
        await api('/api/results', 'POST', {
            play_id: winningPlay.value.id,
            draw_date: selectedDate.value,
            winning_number: String(winningNumber.value).padStart(2, '0'),
        });
        showWinnerDialog.value = false;
        flash(`Ganador de ${winningPlay.value.name} guardado para ${selectedDate.value}.`);
        await loadData();
    } catch (err) { winnerError.value = err.message; }
    finally { busy.value = false; }
}
async function toggleActive(row) {
    const config = configs[module.value];
    const record = { ...row.record, active: !row.record.active };
    const payload = module.value === 'plays'
        ? { name: record.name, draw_time: String(record.draw_time).slice(0, 5), lock_minutes: record.lock_minutes, pieces_per_quetzal: record.pieces_per_quetzal, prize_per_quetzal: record.prize_per_quetzal, active: record.active }
        : module.value === 'points'
            ? { name: record.name, contact_name: record.contact_name, phone: record.phone, active: record.active }
            : { sales_point_id: record.sales_point_id, play_id: record.play_id, name: record.name, commission_percentage: record.commission_percentage, active: record.active };
    try { await api(`${config.endpoint}/${record.id}`, 'PUT', payload); await loadData(); }
    catch (err) { flash(err.message); }
}
function exportCsv() {
    if (module.value === 'settlements') {
        const report = [['Jugada', 'Ventas Q', 'Pedazos', 'Comisiones Q', 'Premios Q', 'Neto Q'], ...data.plays.map(play => [play.name, play.sold, play.pieces, play.commission, play.prizes, Number(play.sold) - Number(play.commission) - Number(play.prizes)])];
        const lines = report.map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob(['\ufeff' + lines], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `lorito-cuadre-${selectedDate.value}.csv`; link.click(); URL.revokeObjectURL(link.href);
        return;
    }
    const lines = [headers.value, ...filteredRows.value.map(row => row.values)].map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\ufeff' + lines], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `lorito-${module.value}-${selectedDate.value}.csv`; link.click(); URL.revokeObjectURL(link.href);
}
function stateClass(state) {
    if (state === 'En venta' || state === 'Con resultado' || state === 'Activo' || state === 'Activa') return 'status-live';
    if (state === 'Cerrada' || state === 'Pendiente de resultado') return 'status-wait';
    return 'status-off';
}
function barWidth(play) {
    const max = Math.max(...data.plays.map(item => Number(item.sold)), 1);
    return `${Math.max(3, (Number(play.sold) / max) * 100)}%`;
}
function listSalesAmount(listId) {
    return data.sales.filter(sale => Number(sale.sales_list_id) === Number(listId)).reduce((sum, sale) => sum + Number(sale.amount_quetzales), 0);
}
function listCommissionAmount(listId) {
    return data.sales.filter(sale => Number(sale.sales_list_id) === Number(listId)).reduce((sum, sale) => sum + Number(sale.amount_quetzales) * Number(sale.commission_percentage) / 100, 0);
}
function numberBreakdown(listId, play) {
    const grouped = new Map();
    for (const sale of data.sales.filter(row => Number(row.sales_list_id) === Number(listId) && Number(row.play_id) === Number(play.id))) {
        const key = String(sale.number).padStart(2, '0');
        const row = grouped.get(key) ?? { number: key, amount: 0, pieces: 0, commission: 0, prize: 0 };
        const amount = Number(sale.amount_quetzales);
        row.amount += amount;
        row.pieces += amount * Number(sale.pieces_per_quetzal);
        row.commission += amount * Number(sale.commission_percentage) / 100;
        if (play.winning_number && key === String(play.winning_number).padStart(2, '0')) row.prize += amount * Number(sale.prize_per_quetzal);
        grouped.set(key, row);
    }
    return [...grouped.values()].sort((a, b) => Number(a.number) - Number(b.number));
}

onMounted(checkSession);
onUnmounted(() => window.clearInterval(refreshTimer));
</script>

<template>
    <div v-if="loading" class="grid min-h-screen place-items-center bg-[#f8f7ee]"><div class="flex items-center gap-3 text-sm font-bold text-[#245b3d]"><span class="spinner"></span> Abriendo el puesto...</div></div>

    <main v-else-if="!user" class="login-bg relative grid min-h-screen place-items-center overflow-hidden px-4 py-10">
        <div class="login-orb login-orb-one"></div><div class="login-orb login-orb-two"></div>
        <div class="login-parrot" aria-hidden="true"><img :src="'/parrot.svg'" alt="" class="parrot-img"/></div>
        <section class="relative z-10 grid w-full max-w-[940px] overflow-hidden rounded-[30px] bg-white shadow-[0_30px_90px_#183d2822] md:grid-cols-[1.02fr_.98fr]">
            <div class="login-art relative hidden min-h-[560px] overflow-hidden p-10 text-white md:flex md:flex-col md:justify-between">
                <div class="relative z-10"><div class="flex items-center gap-3"><BrandMark /><div><div class="font-display text-xl font-extrabold">Lorito<span class="text-[#fbd34b]">.</span></div><div class="text-[10px] font-bold tracking-[.2em] text-[#d4ebd6]">LA SUERTE EN TUS MANOS</div></div></div>
                    <div class="mt-16"><span class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-[10px] font-bold tracking-wide"><Sparkles :size="12" /> PUERTO BARRIOS, GUATEMALA</span><h1 class="max-w-sm font-display text-[40px] font-extrabold leading-[1.08] tracking-[-.045em]">Cada número<br>cuenta una historia<span class="text-[#fbd34b]">.</span></h1><p class="mt-4 max-w-xs text-sm leading-6 text-[#e0eddf]">Las jugadas, tus puntos y cada cuadre; todo en un mismo lugar.</p></div></div>
                <div class="relative z-10 flex items-end justify-between"><div><div class="font-display text-4xl font-extrabold">¡Buena suerte!</div><div class="mt-1 text-xs text-[#d4ebd6]">Que los números siempre te acompañen.</div></div><div class="lottery-balls"><span>7</span><span>2</span><span>9</span></div></div>
                <div class="absolute -bottom-28 -right-20 size-[340px] rounded-full border border-white/10"></div><div class="absolute -bottom-16 -right-8 size-[245px] rounded-full border border-white/10"></div><div class="absolute right-12 top-24 size-3 rounded-full bg-[#fbd34b] shadow-[0_0_30px_8px_#fbd34b55]"></div>
            </div>
            <div class="flex min-h-[560px] flex-col justify-center px-7 py-10 sm:px-12">
                <div class="mb-8 flex items-center gap-3 md:hidden"><BrandMark /><div><div class="font-display text-xl font-extrabold">Lorito<span class="text-[#ecad29]">.</span></div><div class="text-[10px] font-bold tracking-[.15em] text-[#819087]">CONTROL DE VENTAS</div></div></div>
                <span class="mb-3 text-[10px] font-extrabold tracking-[.18em] text-[#41825a]">BIENVENIDO DE VUELTA</span><h2 class="font-display text-[30px] font-extrabold tracking-[-.045em] text-[#203d2d]">Ingresa a tu puesto</h2><p class="mt-2 text-sm text-[#849189]">Administra las jugadas con todo bajo control.</p>
                <form class="mt-8 space-y-4" @submit.prevent="signIn">
                    <label class="field-label">Correo electrónico<input v-model="login.email" type="email" autocomplete="username" required placeholder="admin@lorito.local" class="field-input" /></label>
                    <label class="field-label">Contraseña<input v-model="login.password" type="password" autocomplete="current-password" required class="field-input" /></label>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-[#738078]"><input v-model="login.remember" type="checkbox" class="accent-[#28734b]" /> Mantener sesión iniciada</label>
                    <div v-if="error" class="rounded-xl border border-[#f4d6c9] bg-[#fff4ef] px-3 py-2.5 text-xs font-semibold text-[#ae5636]">{{ error }}</div>
                    <button :disabled="busy" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#1f623f] text-sm font-extrabold text-white shadow-lg shadow-[#235d3c22] transition hover:-translate-y-0.5 hover:bg-[#174e31] disabled:opacity-60">{{ busy ? 'Ingresando…' : 'Entrar al sistema' }} <ChevronRight :size="16" /></button>
                </form>
                <div class="mt-5 flex justify-center gap-4 text-xs font-bold"><a href="/register" class="text-[#39764b]">Crear espacio de venta</a><a href="/superadmin/login" class="text-[#829087]">Superadministrador</a></div>
                <p class="mt-7 text-center text-[10px] text-[#9aa39d]">Acceso protegido · Lorito Control © 2026</p>
            </div>
        </section>
    </main>

    <div v-else class="min-h-screen bg-[#f8f8f2] text-[#20352a]">
        <div class="flyby" aria-hidden="true"><img :src="'/parrot.svg'" alt="" class="parrot-img"/></div>
        <aside class="sidebar fixed inset-y-0 left-0 z-20 hidden w-[252px] flex-col px-5 py-6 lg:flex">
            <div class="mb-9 flex items-center gap-3 px-2"><BrandMark /><div><div class="font-display text-[19px] font-extrabold tracking-tight text-[#1c462f]">Lorito<span class="text-[#edae2c]">.</span></div><div class="text-[9px] font-extrabold tracking-[.17em] text-[#8a9b8f]">CONTROL DE VENTAS</div></div></div>
            <nav class="flex-1 space-y-6 overflow-y-auto">
                <section v-for="group in groupedNav" :key="group.section"><div class="mb-2 px-3 text-[9px] font-extrabold tracking-[.16em] text-[#98a49b]">{{ group.section }}</div>
                    <button v-for="item in group.items" :key="item.id" @click="startModule(item.id)" class="nav-item mb-1 flex w-full items-center gap-3 rounded-xl px-3 py-[10px] text-left text-[12px] font-bold transition" :class="module === item.id ? 'nav-active' : 'text-[#63736a] hover:bg-[#eff4ed]'">
                        <component :is="item.icon" :size="16" :stroke-width="1.9"/><span class="flex-1">{{ item.label }}</span><span v-if="item.id === 'sales'" class="flex size-[18px] items-center justify-center rounded-full bg-[#fbe6a6] text-[9px] text-[#6d5521]">{{ data.sales.length }}</span>
                    </button>
                </section>
            </nav>
            <div class="mt-4 rounded-2xl bg-[#edf3e9] p-4"><div class="flex items-center gap-2 text-[11px] font-extrabold text-[#315f3f]"><ShieldCheck :size="15"/> El puesto está en orden</div><p class="mt-2 text-[10px] leading-4 text-[#718273]">Las ventas cierran automáticamente antes de cada jugada.</p></div>
            <div class="mt-5 flex items-center gap-3 border-t border-[#e6ebe3] pt-5"><div class="flex size-9 items-center justify-center rounded-full bg-[#f7d66f] text-[11px] font-extrabold text-[#4a462f]">{{ user.name?.split(' ').map(word => word[0]).slice(0, 2).join('') }}</div><div class="min-w-0 flex-1"><div class="truncate text-xs font-extrabold">{{ user.name }}</div><div class="text-[9px] font-semibold text-[#89968c]">{{ admin ? 'Administrador' : 'Vendedor' }}</div></div><button title="Cerrar sesión" @click="signOut" class="rounded-lg p-1.5 text-[#839087] hover:bg-white"><LogOut :size="15"/></button></div>
        </aside>

        <main class="min-w-0 lg:pl-[252px]">
            <header class="topbar sticky top-0 z-10 flex h-[66px] items-center justify-between border-b border-[#e9ede5] bg-[#fffefa]/95 px-4 backdrop-blur sm:px-7">
                <div class="flex items-center gap-3"><button class="rounded-lg p-2 text-[#64746a] lg:hidden" @click="showMobileNav = true"><Menu :size="18"/></button><div class="text-[11px] text-[#96a198]">Tu negocio <span class="mx-1.5 text-[#c4cbc4]">/</span> <span class="font-bold text-[#354d3d]">{{ currentLabel }}</span></div></div>
                <div class="flex items-center gap-2 sm:gap-4"><label class="flex items-center gap-2 rounded-xl border border-[#e9ece5] bg-white px-3 py-2"><CalendarDays :size="14" class="text-[#6e8d71]"/><input v-model="selectedDate" @change="loadData" type="date" class="w-[108px] border-0 bg-transparent p-0 text-[10px] font-bold text-[#54665a] outline-none"/><ChevronDown :size="12" class="hidden text-[#89968d] sm:block"/></label><button class="relative rounded-lg p-2 text-[#79877d]" title="Actualización automática"><Bell :size="17"/><span class="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-[#e7ad2d]"></span></button><div class="hidden h-7 w-px bg-[#e9ede5] sm:block"></div><div class="flex size-8 items-center justify-center rounded-full bg-[#dcebd9] text-[10px] font-extrabold text-[#367147]">{{ user.name?.slice(0, 1) }}</div></div>
            </header>
            <div v-if="showMobileNav" class="fixed inset-0 z-40 bg-[#173523]/40 lg:hidden" @click.self="showMobileNav = false"><nav class="h-full w-[280px] overflow-y-auto bg-[#fffef9] p-5 shadow-xl"><div class="mb-8 flex items-center justify-between"><div class="flex items-center gap-3"><BrandMark/><div class="font-display text-lg font-extrabold">Lorito<span class="text-[#edae2c]">.</span></div></div><button @click="showMobileNav = false" class="rounded-lg p-2"><X :size="17"/></button></div><section v-for="group in groupedNav" :key="group.section" class="mb-6"><div class="mb-2 px-3 text-[9px] font-extrabold tracking-[.16em] text-[#98a49b]">{{ group.section }}</div><button v-for="item in group.items" :key="item.id" @click="startModule(item.id)" class="mb-1 flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-xs font-bold" :class="module === item.id ? 'nav-active' : 'text-[#63736a] hover:bg-[#eff4ed]'"><component :is="item.icon" :size="16"/><span>{{ item.label }}</span></button></section></nav></div>

            <div class="mx-auto max-w-[1460px] px-4 py-6 sm:px-7 sm:py-8">
                <div v-if="toast" class="toast-pop fixed right-5 top-[78px] z-40 flex max-w-sm items-center gap-2 rounded-xl bg-[#234f35] px-4 py-3 text-xs font-bold text-white shadow-xl"><Check :size="15"/> {{ toast }}</div>
                <div v-if="error" class="mb-5 flex items-center justify-between rounded-xl border border-[#f2d3c7] bg-[#fff4ef] px-4 py-3 text-xs font-semibold text-[#a94e34]"><span>{{ error }}</span><button @click="error = ''"><X :size="15"/></button></div>

                <template v-if="module === 'overview'">
                    <section class="hero-banner relative mb-6 overflow-hidden rounded-[22px] px-6 py-7 text-white sm:px-8 sm:py-8">
                        <div class="relative z-[1] max-w-[620px]"><div class="mb-3 flex items-center gap-2 text-[10px] font-extrabold tracking-[.16em] text-[#d9edc7]"><span class="size-2 rounded-full bg-[#ffd449] shadow-[0_0_0_4px_#ffd44925]"></span>{{ todayLabel }}</div><h1 class="font-display text-[27px] font-extrabold leading-tight tracking-[-.045em] sm:text-[34px]">¡Hola, {{ user.name?.split(' ')[0] }}! <span class="text-[#ffdc64]">¿Listos para la suerte?</span></h1><p class="mt-2 text-xs text-[#deebd9] sm:text-sm">Este es el resumen de tu operación. Que no se escape ningún número.</p><div class="mt-5 flex flex-wrap gap-2"><button @click="startModule('sales')" class="flex h-9 items-center gap-2 rounded-lg bg-[#ffdb64] px-3.5 text-[11px] font-extrabold text-[#305035] shadow-sm transition hover:-translate-y-0.5"><Plus :size="14"/> Registrar venta</button><button v-if="admin" @click="startModule('results')" class="flex h-9 items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3.5 text-[11px] font-bold text-white transition hover:bg-white/15"><Trophy :size="14"/> Ingresar resultado</button></div></div>
                        <div class="hero-rings"></div><div class="hero-star hero-star-one">✦</div><div class="hero-star hero-star-two">✦</div><div class="hero-parrot"><img :src="'/parrot.svg'" alt="" class="parrot-img"/></div><div class="hero-ball">7</div>
                    </section>

                    <section class="mb-6 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                        <article v-for="card in dashboardCards" :key="card.label" class="stat-card rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5"><div class="flex items-start justify-between"><span class="text-[11px] font-bold text-[#78867b]">{{ card.label }}</span><span class="flex size-8 items-center justify-center rounded-[11px]" :class="`tone-${card.color}`"><component :is="card.icon" :size="16"/></span></div><div class="mt-4 font-display text-[23px] font-extrabold tracking-tight text-[#233b2b]">{{ card.value }}</div><div class="mt-1.5 text-[10px] text-[#98a299]">{{ card.note }}</div></article>
                    </section>

                    <div class="grid items-start gap-5 2xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,.8fr)]">
                        <section class="overflow-hidden rounded-2xl border border-[#e8ece4] bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#edf0e9] px-5 py-4 sm:px-6"><div><h2 class="text-[13px] font-extrabold">Las jugadas de hoy</h2><p class="mt-1 text-[10px] text-[#98a198]">Ventas y cierre por horario · se actualiza cada 15 segundos</p></div><button @click="startModule('plays')" class="flex items-center gap-1 text-[10px] font-extrabold text-[#438055]">Ver jugadas <ChevronRight :size="13"/></button></div>
                            <div v-if="!data.plays.length" class="px-6 py-12 text-center"><span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-[#f6f3e4] text-[#9a8744]"><Clock3 :size="21"/></span><p class="mt-3 text-xs font-bold">Aún no tienes jugadas</p><p class="mt-1 text-[10px] text-[#929c92]">Crea la primera para empezar a vender.</p><button v-if="admin" @click="startModule('plays'); openCreate()" class="mt-4 rounded-lg bg-[#e9f2e5] px-3 py-2 text-[10px] font-extrabold text-[#3c744d]">Crear jugada</button></div>
                            <div v-else class="divide-y divide-[#f0f2ec]">
                                <div v-for="play in data.plays" :key="play.id" class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6"><div class="flex size-9 shrink-0 items-center justify-center rounded-xl" :class="play.state === 'En venta' ? 'bg-[#e7f2e4] text-[#548354]' : 'bg-[#f4f0df] text-[#9c8540]'"><Ticket :size="16"/></div><div class="min-w-[110px] flex-1"><div class="text-[11px] font-extrabold">{{ play.name }}</div><div class="mt-1 text-[9px] text-[#98a198]">Sorteo {{ time(play.draw_time) }} · cierre {{ time(`${String((Number(play.draw_time.slice(0,2))*60+Number(play.draw_time.slice(3))-play.lock_minutes+1440)%1440/60|0).padStart(2,'0')}:${String((Number(play.draw_time.slice(0,2))*60+Number(play.draw_time.slice(3))-play.lock_minutes+1440)%60).padStart(2,'0')}`) }}</div></div><div class="w-[105px]"><div class="mb-1 flex justify-between text-[9px]"><span class="font-bold">{{ money(play.sold) }}</span><span class="text-[#9da79e]">{{ number(play.pieces) }} ped.</span></div><div class="h-1.5 rounded-full bg-[#f0f2ec]"><div class="h-1.5 rounded-full bg-[#69a269] transition-all" :style="{width:barWidth(play)}"></div></div></div><span class="min-w-[92px] rounded-full px-2.5 py-1 text-center text-[9px] font-extrabold" :class="stateClass(play.state)">{{ play.state }}</span></div>
                            </div>
                            <div class="flex items-center justify-between border-t border-[#edf0e9] bg-[#fcfcf8] px-5 py-3 text-[9px] text-[#97a197] sm:px-6"><span>{{ data.plays.length }} jugadas configuradas</span><span class="flex items-center gap-1.5"><span class="size-1.5 animate-pulse rounded-full bg-[#67a46e]"></span>Actualización en vivo</span></div>
                        </section>
                        <div class="space-y-5">
                            <section class="rounded-2xl border border-[#e8ece4] bg-white p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-[13px] font-extrabold">Cuadre provisional</h2><p class="mt-1 text-[10px] text-[#98a198]">Resumen para {{ selectedDate }}</p></div><span class="flex size-8 items-center justify-center rounded-xl bg-[#f8f1d5] text-[#a08833]"><CircleDollarSign :size="16"/></span></div><div class="space-y-3.5"><div class="flex justify-between text-[11px]"><span class="text-[#77847a]">Ventas recibidas</span><b>{{ money(data.totals.sales) }}</b></div><div class="flex justify-between text-[11px]"><span class="text-[#77847a]">Comisión de puntos</span><b class="text-[#a47829]">− {{ money(data.totals.commission) }}</b></div><div class="flex justify-between text-[11px]"><span class="text-[#77847a]">Premios ganadores</span><b class="text-[#a75655]">− {{ money(data.totals.prizes) }}</b></div><div class="flex items-center justify-between border-t border-dashed border-[#e7ebe3] pt-3"><span class="text-[11px] font-extrabold">Neto estimado</span><b class="font-display text-lg font-extrabold text-[#34764a]">{{ money(data.totals.net) }}</b></div></div><button @click="startModule('settlements')" class="mt-4 flex h-9 w-full items-center justify-center gap-2 rounded-lg border border-[#e2eadf] text-[10px] font-extrabold text-[#467a4f] hover:bg-[#f8fbf5]">Ver cuadre por lista <ChevronRight :size="13"/></button></section>
                            <section class="rounded-2xl border border-[#e8ece4] bg-white p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-[13px] font-extrabold">Últimos números</h2><p class="mt-1 text-[10px] text-[#98a198]">Ventas recientes de hoy</p></div><button @click="startModule('sales')" class="text-[10px] font-extrabold text-[#438055]">Ver todas</button></div><div v-if="!data.sales.length" class="rounded-xl bg-[#fafaf6] px-3 py-5 text-center text-[10px] text-[#98a198]">Todavía no hay ventas registradas.</div><div v-else class="space-y-3"><div v-for="sale in data.sales.slice(0,4)" :key="sale.id" class="flex items-center gap-2.5"><span class="flex size-8 items-center justify-center rounded-xl bg-[#f8f0d6] font-display text-[13px] font-extrabold text-[#98772d]">{{ sale.number }}</span><div class="min-w-0 flex-1"><div class="truncate text-[10px] font-extrabold">{{ sale.play?.name }} · {{ sale.list?.point?.name }}</div><div class="mt-0.5 text-[9px] text-[#a0a89f]">{{ time(new Intl.DateTimeFormat('en-GB',{hour:'2-digit',minute:'2-digit'}).format(new Date(sale.sold_at))) }}</div></div><span class="text-[10px] font-extrabold">{{ money(sale.amount_quetzales) }}</span></div></div></section>
                        </div>
                    </div>
                </template>

                <template v-else-if="module === 'settlements'">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-3"><div><div class="mb-1 text-[10px] font-extrabold tracking-[.14em] text-[#769075]">OPERACIÓN · FINANZAS</div><h1 class="font-display text-[26px] font-extrabold tracking-tight">Cuadre de la jornada</h1><p class="mt-1 text-xs text-[#87948a]">Ventas, comisiones y premios del {{ selectedDate }}. Usa el filtro de fecha superior para consultar otros días.</p></div><button @click="exportCsv" class="flex h-9 items-center gap-2 rounded-lg border border-[#e1e8de] bg-white px-3 text-[10px] font-extrabold text-[#557059]"><ArrowDownToLine :size="14"/> Descargar CSV</button></div>
                    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article v-for="card in dashboardCards" :key="card.label" class="rounded-2xl border border-[#e8ece4] bg-white p-4"><div class="text-[10px] font-bold text-[#7d8a7e]">{{ card.label }}</div><div class="mt-2 font-display text-xl font-extrabold">{{ card.value }}</div></article></div>
                    <div class="overflow-hidden rounded-2xl border border-[#e8ece4] bg-white"><div class="border-b border-[#edf0e9] px-5 py-4"><h2 class="text-xs font-extrabold">Desglose por jugada, lista y número</h2><p class="mt-1 text-[10px] text-[#98a198]">Abre cada lista para ver cuántos pedazos y quetzales se vendieron por número, más su comisión y premio.</p></div><div v-if="!data.plays.length" class="p-12 text-center text-xs text-[#909b91]">Crea jugadas para empezar a ver cuadres.</div><div v-for="play in data.plays" :key="play.id" class="border-b border-[#edf0e9] last:border-0"><div class="flex flex-wrap items-center justify-between gap-3 bg-[#fbfcf7] px-5 py-3"><div class="flex items-center gap-2"><Ticket :size="15" class="text-[#67885e]"/><span class="text-xs font-extrabold">{{ play.name }}</span><span class="rounded-full px-2 py-1 text-[9px] font-bold" :class="stateClass(play.state)">{{ play.state }}</span></div><div class="text-[10px] text-[#899589]">{{ time(play.draw_time) }} · {{ play.winning_number ? `Ganador ${play.winning_number}` : 'Sin resultado' }}</div></div><div class="grid grid-cols-2 gap-y-2 px-5 py-4 text-[10px] sm:grid-cols-5"><div><div class="text-[#95a095]">Vendido</div><b>{{ money(play.sold) }}</b></div><div><div class="text-[#95a095]">Pedazos</div><b>{{ number(play.pieces) }}</b></div><div><div class="text-[#95a095]">Comisión</div><b class="text-[#a47829]">{{ money(play.commission) }}</b></div><div><div class="text-[#95a095]">Premios</div><b class="text-[#a75655]">{{ money(play.prizes) }}</b></div><div><div class="text-[#95a095]">Neto</div><b class="text-[#34764a]">{{ money(play.sold - play.commission - play.prizes) }}</b></div></div><div v-for="list in data.lists.filter(item => item.play_id === play.id)" :key="list.id" class="border-t border-dashed border-[#edf0e9] px-4 py-3 sm:px-6 sm:pl-8"><div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><div><div class="text-xs font-extrabold">{{ list.name }} <span class="font-normal text-[#8e9a90]">· {{ list.point?.name }}</span></div><div class="mt-1 text-[10px] text-[#7e8b81]">Comisión {{ Number(list.commission_percentage).toFixed(2) }}% · {{ money(listCommissionAmount(list.id)) }} de comisión · {{ money(listSalesAmount(list.id)) }} vendido</div></div><div class="text-xs font-extrabold text-[#34764a]">Premios de esta lista: {{ money(numberBreakdown(list.id, play).reduce((sum, row) => sum + row.prize, 0)) }}</div></div><details v-if="numberBreakdown(list.id, play).length" class="mt-3"><summary class="min-h-10 cursor-pointer rounded-lg bg-[#f6f8f2] px-3 py-2.5 text-[11px] font-extrabold text-[#48664d]">Ver {{ numberBreakdown(list.id, play).length }} números vendidos · {{ number(numberBreakdown(list.id, play).reduce((sum, row) => sum + row.pieces, 0)) }} pedazos</summary><div class="mt-2 overflow-x-auto rounded-lg border border-[#edf0e9]"><table class="w-full min-w-[650px] text-left text-[10px]"><thead class="bg-[#f8f9f5] text-[#87948a]"><tr><th class="px-3 py-2">Número</th><th class="px-3 py-2">Pedazos</th><th class="px-3 py-2">Quetzales vendidos</th><th class="px-3 py-2">Comisión</th><th class="px-3 py-2">Premio</th><th class="px-3 py-2">Neto</th></tr></thead><tbody><tr v-for="row in numberBreakdown(list.id, play)" :key="row.number" class="border-t border-[#edf0e9]" :class="play.winning_number && row.number === String(play.winning_number).padStart(2, '0') ? 'bg-[#fff9e5] font-extrabold' : ''"><td class="px-3 py-2.5"><span class="inline-flex min-w-8 justify-center rounded-md bg-[#f4f0df] px-2 py-1 font-display text-sm">{{ row.number }}</span><span v-if="play.winning_number && row.number === String(play.winning_number).padStart(2, '0')" class="ml-2 text-[#947a2d]">GANADOR</span></td><td class="px-3 py-2.5">{{ number(row.pieces) }}</td><td class="px-3 py-2.5">{{ money(row.amount) }}</td><td class="px-3 py-2.5">{{ money(row.commission) }}</td><td class="px-3 py-2.5 text-[#a75655]">{{ money(row.prize) }}</td><td class="px-3 py-2.5 text-[#34764a]">{{ money(row.amount - row.commission - row.prize) }}</td></tr></tbody></table></div></details><div v-else class="mt-2 text-[10px] text-[#929c92]">Esta lista no tuvo ventas en la jugada seleccionada.</div></div></div></div>
                </template>

                <template v-else-if="module === 'results'">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div class="mb-1 text-[10px] font-extrabold tracking-[.14em] text-[#769075]">CIERRE DE JUGADAS</div><h1 class="font-display text-[26px] font-extrabold tracking-tight">Resultados del día</h1><p class="mt-1 text-xs text-[#87948a]">Elige la fecha y registra el ganador de cada jugada para calcular los premios.</p></div><label class="flex min-h-14 items-center gap-3 rounded-xl border border-[#e4eae1] bg-white px-4 text-xs font-extrabold">Fecha del sorteo<input v-model="selectedDate" :max="localDate()" @change="loadData" type="date" class="min-h-10 border-0 bg-transparent text-sm outline-none"/></label></div>
                    <div v-if="!data.plays.length" class="rounded-2xl border border-dashed border-[#dfe7dc] bg-white px-5 py-14 text-center"><Trophy :size="26" class="mx-auto text-[#b89b44]"/><p class="mt-3 text-sm font-extrabold">No hay jugadas configuradas</p><p class="mt-1 text-xs text-[#8b978c]">Primero agrega las jugadas que se realizan cada día.</p></div>
                    <div v-else class="grid gap-3 lg:grid-cols-2"> <article v-for="play in data.plays" :key="play.id" class="rounded-2xl border border-[#e8ece4] bg-white p-4 shadow-sm sm:p-5"><div class="flex items-start justify-between gap-3"><div><h2 class="text-base font-extrabold">{{ play.name }}</h2><p class="mt-1 text-xs text-[#87948a]">Sorteo diario · {{ time(play.draw_time) }}</p></div><span class="rounded-full px-3 py-1 text-[10px] font-extrabold" :class="stateClass(play.state)">{{ play.state }}</span></div><div class="mt-4 flex items-center gap-4 rounded-xl bg-[#fafaf6] p-3"><span class="flex size-14 shrink-0 items-center justify-center rounded-2xl" :class="play.winning_number ? 'bg-[#f7edcb] text-[#8b712c]' : 'bg-[#edf2ea] text-[#829083]'"><span class="font-display text-2xl font-extrabold">{{ play.winning_number ?? '—' }}</span></span><div><div class="text-xs font-extrabold">{{ play.winning_number ? 'Número ganador' : 'Ganador pendiente' }}</div><div class="mt-1 text-[11px] text-[#7f8c81]">{{ play.winning_number ? `${money(play.matched_amount)} vendidos · ${money(play.prizes)} en premios` : 'Ingresa el resultado de ' + selectedDate }}</div></div></div><button v-if="admin" @click="saveResult(play)" class="mt-4 min-h-12 w-full rounded-xl bg-[#25663f] px-4 text-sm font-extrabold text-white active:scale-[.99]">{{ play.winning_number ? 'Corregir número ganador' : 'Ingresar número ganador' }}</button><p v-else class="mt-4 text-center text-xs text-[#859187]">El administrador registra el resultado de esta jugada.</p></article></div>
                </template>

                <template v-else-if="module === 'sales'">
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><div class="mb-1 text-[10px] font-extrabold tracking-[.14em] text-[#769075]">VENTA RÁPIDA</div><h1 class="font-display text-[26px] font-extrabold tracking-tight">Elige y vende</h1><p class="mt-1 text-xs text-[#87948a]">Toca una jugada y los números. Agrega todo al mismo comprobante.</p></div><div class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#526b58]">{{ time(new Date().toTimeString().slice(0,5)) }} · Puerto Barrios</div></div>
                    <div v-if="admin" class="mb-4 rounded-2xl border border-[#e8ece4] bg-white p-4"><label class="block text-xs font-extrabold">¿En qué punto estás vendiendo?<select v-model="salePointId" class="field-input mt-2 min-h-12 text-base"><option value="">Selecciona un punto</option><option v-for="point in data.points.filter(item => item.active)" :key="point.id" :value="point.id">{{ point.name }}</option></select></label></div>
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_330px]">
                        <div class="space-y-4">
                            <section class="rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5"><div class="mb-3 flex items-center justify-between"><div><div class="text-sm font-extrabold">1. ¿Qué jugada?</div><div class="mt-1 text-[11px] text-[#8b978c]">Puedes escoger varias</div></div><span class="rounded-full bg-[#edf5e9] px-3 py-1 text-xs font-extrabold text-[#367049]">{{ selectedSalePlays.length }} elegida(s)</span></div><div v-if="!salePlays.length" class="rounded-xl bg-[#fff9e5] p-4 text-sm text-[#8c722c]">No hay jugadas abiertas para este punto.</div><div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"><button v-for="play in salePlays" :key="play.id" @click="toggleSalePlay(play.id)" class="min-h-[76px] rounded-2xl border-2 p-3 text-left transition active:scale-[.98]" :class="selectedSalePlays.includes(play.id) ? 'border-[#32804b] bg-[#eef8ed]' : 'border-[#edf0e9] bg-[#fcfcf9] hover:border-[#a7c8a5]'"><span class="flex items-center justify-between gap-2"><span class="text-sm font-extrabold">{{ play.name }}</span><Check v-if="selectedSalePlays.includes(play.id)" :size="18" class="text-[#32804b]"/></span><span class="mt-1 block text-[11px] text-[#7e8b81]">Sorteo {{ time(play.draw_time) }} · cierra {{ closeTime(play) }}</span></button></div></section>
                            <section class="rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5"><div class="mb-3 flex items-center justify-between"><div><div class="text-sm font-extrabold">2. Toca los números</div><div class="mt-1 text-[11px] text-[#8b978c]">Puedes marcar más de uno</div></div><button v-if="selectedSaleNumbers.length" @click="selectedSaleNumbers=[]" class="min-h-10 rounded-lg px-3 text-xs font-bold text-[#9c5543]">Limpiar selección</button></div><div class="number-grid"><button v-for="value in saleNumbers" :key="value" @click="toggleSaleNumber(value)" class="number-tile" :class="selectedSaleNumbers.includes(value) ? 'number-tile-selected' : ''">{{ value }}</button></div><div class="mt-3 text-center text-xs font-bold text-[#54785a]">{{ selectedSaleNumbers.length }} número(s) marcados</div></section>
                            <section class="rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5"><div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end"><label class="block text-xs font-extrabold">3. ¿Cuánto juega en cada número?<div class="mt-2 flex h-14 items-center rounded-xl border border-[#e5eae1] px-4 focus-within:border-[#74a879]"><span class="mr-2 text-base text-[#758278]">Q</span><input v-model="saleAmount" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0.25" class="w-full text-xl font-extrabold outline-none"/></div></label><button @click="addSaleSelection" :disabled="!selectedSaleLineCount || !chosenSalePoint" class="min-h-14 rounded-xl bg-[#25663f] px-6 text-sm font-extrabold text-white shadow-sm disabled:opacity-40">Agregar {{ selectedSaleLineCount || '' }} número(s)</button></div><div class="mt-2 text-[11px] text-[#8b978c]">El monto se aplica a cada número en cada jugada seleccionada.</div></section>
                        </div>
                        <aside class="h-fit rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5 xl:sticky xl:top-5"><div class="flex items-center justify-between"><div><h2 class="text-base font-extrabold">Comprobante</h2><p class="mt-1 text-[11px] text-[#8b978c]">{{ saleCart.length }} número(s)</p></div><Ticket :size="20" class="text-[#9b8132]"/></div><label class="mt-4 block text-xs font-bold text-[#69776d]">Nombre del cliente (opcional)<input v-model="saleCustomer" class="field-input mt-1.5 min-h-11" placeholder="Cliente"/></label><div class="mt-4 max-h-[38vh] space-y-2 overflow-y-auto"><div v-if="!saleCart.length" class="rounded-xl bg-[#fafaf6] px-3 py-6 text-center text-xs text-[#939d94]">Los números agregados aparecerán aquí.</div><div v-for="(item,index) in saleCart" :key="`${item.sales_list_id}-${item.number}`" class="flex items-center gap-2 rounded-xl bg-[#f8faf5] p-2.5"><span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#f7edcb] font-display text-lg font-extrabold text-[#8b712c]">{{ item.number }}</span><div class="min-w-0 flex-1"><div class="truncate text-xs font-extrabold">{{ item.play_name }}</div><div class="truncate text-[10px] text-[#8d988f]">{{ item.list_name }}</div></div><b class="text-xs">{{ money(item.amount_quetzales) }}</b><button @click="saleCart.splice(index,1)" aria-label="Quitar número" class="flex size-9 items-center justify-center rounded-lg text-[#a05443] hover:bg-[#fff0ec]"><X :size="16"/></button></div></div><div class="mt-4 flex items-center justify-between border-t border-dashed border-[#e5eae1] pt-4"><span class="text-sm font-extrabold">Total a cobrar</span><b class="font-display text-2xl font-extrabold text-[#2e7044]">{{ money(saleLines) }}</b></div><div v-if="error" class="mt-3 rounded-xl bg-[#fff2ed] p-3 text-xs font-bold text-[#aa523a]">{{ error }}</div><button @click="issueSaleTicket" :disabled="busy || !saleCart.length || !chosenSalePoint" class="mt-4 flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-[#e6b93d] px-4 text-sm font-extrabold text-[#3c3117] shadow-sm disabled:opacity-40"><Ticket :size="18"/>{{ busy ? 'Guardando venta…' : 'Cobrar y preparar comprobante' }}</button><button v-if="issuedTicket" class="mt-2 flex min-h-12 w-full items-center justify-center rounded-xl border-2 border-[#3c8551] bg-[#f1f8ed] px-4 text-sm font-extrabold text-[#347049]" @click="openReceipt">Ver PDF {{ issuedTicket.ticket_code }}</button><a v-if="issuedTicket" :href="issuedTicket.receipt_url" target="_blank" class="mt-2 flex min-h-12 w-full items-center justify-center rounded-xl text-xs font-bold text-[#607a65]">Abrir / imprimir comprobante</a></aside>
                    </div>
                    <section class="mt-5 rounded-2xl border border-[#e8ece4] bg-white p-4 sm:p-5"><div class="mb-3 flex items-center justify-between"><div><h2 class="text-sm font-extrabold">Ventas recientes</h2><p class="mt-1 text-[11px] text-[#8b978c]">Movimientos de {{ selectedDate }}</p></div><span class="rounded-full bg-[#f7f2df] px-3 py-1 text-xs font-extrabold text-[#88712b]">{{ data.sales.length }}</span></div><div v-if="!data.sales.length" class="rounded-xl bg-[#fafaf6] p-5 text-center text-xs text-[#939d94]">Todavía no hay ventas para esta fecha.</div><div v-else class="divide-y divide-[#edf0e9]"><div v-for="sale in data.sales.slice(0,10)" :key="sale.id" class="flex items-center gap-3 py-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#f7edcb] font-display text-lg font-extrabold text-[#8b712c]">{{ sale.number }}</span><div class="min-w-0 flex-1"><div class="truncate text-xs font-extrabold">{{ sale.play?.name }} · {{ money(sale.amount_quetzales) }}</div><div class="truncate text-[10px] text-[#8d988f]">{{ sale.list?.point?.name }} · {{ sale.ticket?.ticket_code ?? 'Venta anterior' }}</div></div><a v-if="sale.ticket" :href="receiptUrl(sale)" target="_blank" class="flex min-h-10 items-center rounded-lg bg-[#edf5e9] px-3 text-[11px] font-extrabold text-[#367049]">Comprobante</a></div></div></section>
                </template>

                <template v-else>
                    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-1 flex items-center gap-2 text-[10px] font-extrabold tracking-[.14em] text-[#789076]"><Feather :size="13"/> TU NEGOCIO, BIEN ORGANIZADO</div><h1 class="font-display text-[26px] font-extrabold tracking-tight">{{ moduleTitle }}</h1><p class="mt-1 text-xs text-[#87948a]">{{ moduleDesc }}</p></div><div class="flex flex-wrap items-center gap-2"><button v-if="module === 'sales' || module === 'settlements'" @click="exportCsv" class="flex h-9 items-center gap-2 rounded-lg border border-[#e1e8de] bg-white px-3 text-[10px] font-extrabold text-[#557059]"><ArrowDownToLine :size="14"/> Exportar</button><button v-if="canCreate" @click="openCreate" class="flex h-9 items-center gap-2 rounded-lg bg-[#24633f] px-3.5 text-[10px] font-extrabold text-white shadow-sm hover:bg-[#194e31]"><Plus :size="14"/> {{ moduleAction }}</button></div></div>
                    <div v-if="module === 'users'" class="mb-4 grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-[#e8ece4] bg-white p-4"><div class="text-[10px] font-bold text-[#87948a]">Administradores</div><div class="mt-1 text-sm font-extrabold">1 de 1 · propietario del negocio</div></div><div class="rounded-xl border border-[#e8ece4] bg-white p-4"><div class="text-[10px] font-bold text-[#87948a]">Vendedores</div><div class="mt-1 text-sm font-extrabold">{{ sellerCount }} de 2 cuentas utilizadas</div></div></div>
                    <div v-if="module === 'results'" class="mb-4 flex flex-col gap-3 rounded-2xl border border-[#e8ece4] bg-white p-4 sm:flex-row sm:items-center sm:justify-between"><div><div class="text-sm font-extrabold">Resultados por día</div><div class="mt-1 text-[11px] text-[#8b978c]">Elige la fecha del sorteo. Cada jugada guarda su propio número ganador en ese día.</div></div><label class="flex min-h-12 items-center gap-2 rounded-xl border border-[#e4eae1] px-3 text-xs font-extrabold">Día<input v-model="selectedDate" @change="loadData" type="date" class="min-h-10 border-0 bg-transparent text-sm outline-none"/></label></div>
                    <div v-if="module === 'sales' && !activePlays.length" class="mb-4 flex gap-3 rounded-xl border border-[#eadfb8] bg-[#fff9e4] px-4 py-3 text-[10px] text-[#856d2c]"><Clock3 :size="15" class="mt-0.5 shrink-0"/><span>Las jugadas activas ya cerraron ventas o aún no hay jugadas. El cierre se aplica automáticamente con la hora y los minutos configurados.</span></div>
                    <section class="overflow-hidden rounded-2xl border border-[#e8ece4] bg-white"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#edf0e9] px-4 py-3.5 sm:px-5"><div class="text-[10px] font-bold text-[#8b978c]">{{ number(filteredRows.length) }} registro(s)</div><label class="flex h-8 items-center gap-2 rounded-lg border border-[#edf0e9] px-2.5 text-[#929e93]"><Search :size="13"/><input v-model="search" class="w-32 text-[10px] outline-none placeholder:text-[#a9b0a8]" placeholder="Buscar..."/></label></div>
                        <div v-if="loading" class="grid place-items-center py-14 text-xs text-[#849083]"><span class="spinner"></span></div>
                        <div v-else-if="!filteredRows.length" class="px-5 py-14 text-center"><span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-[#f5f2e4] text-[#9b8540]"><ClipboardList :size="21"/></span><p class="mt-3 text-xs font-extrabold">No hay registros todavía</p><p class="mt-1 text-[10px] text-[#929c92]">{{ isReadOnly ? 'Los registros aparecerán aquí al realizar movimientos.' : 'Agrega el primer registro para empezar.' }}</p><button v-if="canCreate" @click="openCreate" class="mt-4 rounded-lg bg-[#edf4e8] px-3 py-2 text-[10px] font-extrabold text-[#3e7247]">{{ moduleAction }}</button></div>
                        <div v-else class="overflow-x-auto"><table class="w-full min-w-[760px] text-left"><thead><tr class="bg-[#fbfcf8] text-[9px] font-extrabold uppercase tracking-[.09em] text-[#98a397]"><th v-for="heading in headers" :key="heading" class="px-4 py-3.5 first:pl-5">{{ heading }}</th><th v-if="admin && !isReadOnly" class="px-4 py-3">Acciones</th><th v-if="module === 'results'" class="px-4 py-3">Acción</th></tr></thead><tbody><tr v-for="row in filteredRows" :key="row.id" class="border-t border-[#f0f2ec] text-[10px] hover:bg-[#fdfdf9]"><td v-for="(value, index) in row.values" :key="index" class="px-4 py-3.5 first:pl-5" :class="index === 0 ? 'font-extrabold text-[#344839]' : 'text-[#647269]'"><span v-if="['En venta','Cerrada','Con resultado','Pendiente de resultado','Pausada','Activo','Activa','Pausado','Inactiva'].includes(value)" class="rounded-full px-2.5 py-1 text-[9px] font-extrabold" :class="stateClass(value)">{{ value }}</span><span v-else>{{ value }}</span></td><td v-if="admin && !isReadOnly" class="px-4 py-3"><div class="flex items-center gap-1"><button v-if="module !== 'sales' && (module !== 'users' || row.record.role === 'seller')" @click="editRow(row)" title="Editar" class="rounded-md p-1.5 text-[#718172] hover:bg-[#edf3e9]"><Pencil :size="13"/></button><button v-if="admin && ['plays','points','lists'].includes(module)" @click="toggleActive(row)" :title="row.record.active ? 'Pausar' : 'Activar'" class="rounded-md p-1.5 text-[#718172] hover:bg-[#edf3e9]"><component :is="row.record.active ? Minus : Check" :size="13"/></button><button v-if="module !== 'users' || row.record.role === 'seller'" @click="removeRow(row)" title="Eliminar" class="rounded-md p-1.5 text-[#b35e4c] hover:bg-[#fff1ec]"><X :size="14"/></button></div></td><td v-if="module === 'results'" class="px-4 py-3"><button v-if="admin && row.record.state !== 'En venta' && row.record.state !== 'Cerrada'" @click="saveResult(row.record)" class="min-h-10 rounded-lg bg-[#f7efce] px-3 py-2 text-[11px] font-extrabold text-[#806b2c]">{{ row.record.winning_number ? 'Corregir ganador' : 'Ingresar ganador' }}</button><span v-else-if="admin" class="text-[10px] text-[#919c92]">Disponible después del sorteo</span></td></tr></tbody></table></div>
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[#edf0e9] bg-[#fcfcf8] px-5 py-3 text-[9px] text-[#99a298]"><span>Los cierres usan la hora local de Guatemala.</span><span v-if="module === 'sales'">Solo se muestran las ventas de la fecha seleccionada.</span></div>
                    </section>
                </template>
            </div>
        </main>

        <Transition><div v-if="showForm" class="fixed inset-0 z-30 flex items-center justify-center bg-[#173523]/45 p-4 backdrop-blur-[2px]" @click.self="showForm = false"><section class="modal-card max-h-[92vh] w-full max-w-[510px] overflow-y-auto rounded-[22px] bg-white p-5 shadow-2xl sm:p-7"><div class="mb-5 flex items-start justify-between"><div><span class="text-[9px] font-extrabold tracking-[.16em] text-[#70906c]">LORITO · {{ currentLabel.toUpperCase() }}</span><h2 class="mt-1 font-display text-xl font-extrabold">{{ editingId ? 'Editar registro' : moduleAction }}</h2><p class="mt-1 text-[10px] text-[#8c998e]">Completa los datos para mantener tu operación al día.</p></div><button @click="showForm = false" class="rounded-lg p-2 text-[#859187] hover:bg-[#f2f5ef]"><X :size="17"/></button></div>
                <form class="space-y-3.5" @submit.prevent="saveForm"><div v-for="field in fields" :key="field.key" class="block" :class="field.type === 'checkbox' ? 'flex items-center gap-2.5 rounded-xl bg-[#f7f9f3] p-3' : ''"><template v-if="field.type === 'select'"><label :for="`field-${field.key}`" class="field-label-text">{{ field.label }}</label><select :id="`field-${field.key}`" v-model="form[field.key]" :required="field.required" class="field-input mt-1.5"><option v-for="option in field.options()" :key="option.value" :value="option.value">{{ option.label }}</option></select></template><template v-else-if="field.type === 'permissions'"><label class="field-label-text">{{ field.label }}</label><span class="mt-1 block text-[10px] text-[#89968c]">Selecciona las pantallas disponibles para esta cuenta.</span><span class="mt-2 grid gap-2 sm:grid-cols-2"><label v-for="option in field.options" :key="option.value" class="flex cursor-pointer items-start gap-2 rounded-xl border border-[#e7ece3] p-3"><input v-model="form[field.key]" type="checkbox" :value="option.value" class="mt-0.5 size-4 shrink-0 accent-[#3d8250]"/><span><span class="block text-[11px] font-extrabold">{{ option.label }}</span><span class="mt-0.5 block text-[9px] leading-4 text-[#89968c]">{{ option.description }}</span></span></label></span></template><template v-else-if="field.type === 'checkbox'"><input :id="`field-${field.key}`" v-model="form[field.key]" type="checkbox" class="size-4 accent-[#3d8250]"/><label :for="`field-${field.key}`" class="text-[10px] font-bold">{{ field.label }}</label></template><template v-else><label :for="`field-${field.key}`" class="field-label-text">{{ field.label }}</label><input v-model="form[field.key]" :id="`field-${field.key}`" :type="field.type || 'text'" :required="field.required && !(module === 'users' && field.key === 'password' && editingId)" :min="field.min" :max="field.max" :step="field.step" :maxlength="field.maxlength" :inputmode="field.inputmode" :placeholder="field.placeholder" class="field-input mt-1.5"/></template></div>
                    <div v-if="module === 'plays'" class="flex gap-2 rounded-xl bg-[#f6f8f0] p-3 text-[10px] leading-5 text-[#758371]"><Sparkles :size="15" class="mt-0.5 shrink-0 text-[#bf9c37]"/> Configura los pedazos y el premio por cada quetzal. Por ejemplo, 80 pedazos y Q80 de premio equivalen a 400 pedazos y Q400 por Q5.</div>
                    <div v-if="module === 'sales'" class="rounded-xl bg-[#f6f8f0] p-3 text-[10px] text-[#758371]">Cada venta guarda la comisión y las reglas de premio vigentes al momento del registro.</div>
                    <div v-if="error" class="rounded-xl bg-[#fff2ed] px-3 py-2.5 text-[10px] font-bold text-[#aa523a]">{{ error }}</div><div class="flex justify-end gap-2 pt-2"><button type="button" @click="showForm = false" class="rounded-lg px-4 py-2.5 text-[10px] font-bold text-[#69786d]">Cancelar</button><button :disabled="busy" class="rounded-lg bg-[#24633f] px-4 py-2.5 text-[10px] font-extrabold text-white disabled:opacity-60">{{ busy ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Guardar' }}</button></div>
                </form></section></div></Transition>
        <Transition><div v-if="showWinnerDialog" class="fixed inset-0 z-40 flex items-center justify-center bg-[#173523]/50 p-4 backdrop-blur-[2px]" @click.self="showWinnerDialog = false"><section class="w-full max-w-[420px] rounded-[22px] bg-white p-6 shadow-2xl"><div class="flex items-start justify-between"><div><span class="text-[10px] font-extrabold tracking-[.14em] text-[#769075]">RESULTADO DEL DÍA</span><h2 class="mt-1 font-display text-xl font-extrabold">{{ winningPlay?.name }}</h2><p class="mt-1 text-xs text-[#87948a]">Sorteo del {{ selectedDate }}</p></div><button @click="showWinnerDialog = false" class="rounded-lg p-2 text-[#859187] hover:bg-[#f2f5ef]"><X :size="17"/></button></div><form class="mt-5" @submit.prevent="submitWinner"><label class="block text-sm font-extrabold">Número ganador<input v-model="winningNumber" type="number" min="0" max="99" step="1" inputmode="numeric" placeholder="00" class="field-input mt-2 min-h-16 text-center font-display text-3xl font-extrabold" autofocus/></label><p class="mt-2 text-center text-xs text-[#87948a]">Escribe un número del 00 al 99.</p><div v-if="winnerError" class="mt-3 rounded-xl bg-[#fff2ed] px-3 py-2.5 text-xs font-bold text-[#aa523a]">{{ winnerError }}</div><div class="mt-5 flex justify-end gap-2"><button type="button" @click="showWinnerDialog = false" class="min-h-11 rounded-lg px-4 text-xs font-bold text-[#69786d]">Cancelar</button><button :disabled="busy" class="min-h-11 rounded-lg bg-[#24633f] px-5 text-xs font-extrabold text-white disabled:opacity-60">{{ busy ? 'Guardando…' : 'Guardar ganador' }}</button></div></form></section></div></Transition>
    </div>
</template>

<script>
const BrandMark = {
    template: `<span class="brand-mark"><svg viewBox="0 0 44 44" fill="none" aria-hidden="true"><rect width="44" height="44" rx="15" fill="#25633F"/><path d="M11 29c5-11 13-15 22-15-1 10-6 18-16 18-3 0-5-1-6-3Z" fill="#FBD54E"/><path d="M14 31c4-6 9-10 16-14" stroke="#FFF8D7" stroke-width="2" stroke-linecap="round"/><circle cx="28.5" cy="17.5" r="1.2" fill="#245137"/><path d="M31 19l5 1.5-5 1" fill="#E98538"/></svg></span>`,
};
const Parrot = {
    template: `<svg class="parrot-svg" viewBox="0 0 140 110" fill="none" aria-label="Lorito volando"><g class="parrot-body"><path d="M40 61c-3-17 9-34 26-37 15-3 29 7 32 22 3 16-8 31-23 35-15 4-31-4-35-20Z" fill="#45A65C"/><path d="M50 55c3-11 15-18 27-15 11 2 18 12 16 23-3 11-15 18-26 16-12-2-19-12-17-24Z" fill="#72C75D"/><path d="M93 43c9-12 21-14 30-7-8 2-13 8-17 17-4 7-10 10-17 8" fill="#F3BE32"/><path d="M98 47l20-9-13 19" fill="#EF7C3B"/><path d="M40 54c-13-10-23-11-31-5 9 1 15 6 20 14 4 7 9 11 17 9" fill="#1F8747"/><path d="M50 73c-9 12-19 16-29 12 8-4 12-10 15-18" fill="#267844"/><path d="M58 80l-2 10m17-10 2 10" stroke="#EA9B27" stroke-width="4" stroke-linecap="round"/><path d="M51 91l-7 3m7-3 1 5m21-5 7 3m-7-3-1 5" stroke="#E88730" stroke-width="2.5" stroke-linecap="round"/><circle cx="87" cy="39" r="6" fill="white"/><circle cx="89" cy="39" r="2.6" fill="#173B2A"/><path d="M48 48c7-6 17-8 25-6" stroke="#B2E27B" stroke-width="3" stroke-linecap="round"/><g class="parrot-wing"><path d="M67 48c-7-15-4-28 9-37 2 14 8 23 18 29" fill="#29984F" stroke="#187D3F" stroke-width="2"/><path d="M70 39c3-8 7-14 13-20" stroke="#80D466" stroke-width="3" stroke-linecap="round"/></g></g><path d="M6 68c-3 2-4 5-4 8m12-1c-3 2-4 5-4 8m117-45 8-2m-7 8 7 1" stroke="#D6AA37" stroke-width="2" stroke-linecap="round" opacity=".8"/></svg>`,
};
export default { components: { BrandMark, Parrot } };
</script>
