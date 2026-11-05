import './bootstrap';
import Chart from 'chart.js/auto';
import Sortable from 'sortablejs';
import { mountAll as mountLoginViz } from './login-viz';

window.Chart = Chart;
window.Sortable = Sortable;

document.addEventListener('DOMContentLoaded', mountLoginViz);
document.addEventListener('livewire:navigated', mountLoginViz);
