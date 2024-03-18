import{_ as Pe}from"./Authenticated.29c5076b.js";import{s as w,g as m,B as T,z as x,h as V,P,c as Z,a as C,r as K,W as I,C as y,l as J,m as Ie,I as Le,F as fe,j as $,L as ye,a8 as _,a9 as Y,aa as Q,ab as ee,o as L,ac as ke,w as G,u as H,f as Ee,Z as qe,b as O}from"./app.c8734f48.js";import"./keyboard.d1255e04.js";import"./use-resolve-button-type.cb4535c6.js";import"./RectangleStackIcon.08fda477.js";let E=Symbol("map"),q=Symbol("api"),we=Symbol("marker"),xe=Symbol("markerCluster"),A=Symbol("CustomMarker"),Ce=Symbol("mapTilesLoaded"),S="click dblclick drag dragend dragstart mousedown mousemove mouseout mouseover mouseup rightclick".split(" ");var je=function i(e,t){if(e===t)return!0;if(e&&t&&typeof e=="object"&&typeof t=="object"){if(e.constructor!==t.constructor)return!1;var r;if(Array.isArray(e)){var o=e.length;if(o!=t.length)return!1;for(r=o;r--!==0;)if(!i(e[r],t[r]))return!1;return!0}if(e.constructor===RegExp)return e.source===t.source&&e.flags===t.flags;if(e.valueOf!==Object.prototype.valueOf)return e.valueOf()===t.valueOf();if(e.toString!==Object.prototype.toString)return e.toString()===t.toString();var s=Object.keys(e);if(o=s.length,o!==Object.keys(t).length)return!1;for(r=o;r--!==0;)if(!Object.prototype.hasOwnProperty.call(t,s[r]))return!1;for(r=o;r--!==0;)if(o=s[r],!i(e[o],t[o]))return!1;return!0}return e!==e&&t!==t};class M{constructor({apiKey:e,channel:t,client:r,id:o="__googleMapsScriptId",libraries:s=[],language:u,region:n,version:d,mapIds:l,nonce:a,retries:p=3,url:h="https://maps.googleapis.com/maps/api/js"}){if(this.CALLBACK="__googleMapsCallback",this.callbacks=[],this.loading=this.done=!1,this.errors=[],this.version=d,this.apiKey=e,this.channel=t,this.client=r,this.id=o||"__googleMapsScriptId",this.libraries=s,this.language=u,this.region=n,this.mapIds=l,this.nonce=a,this.retries=p,this.url=h,M.instance){if(!je(this.options,M.instance.options))throw Error(`Loader must not be called again with different options. ${JSON.stringify(this.options)} !== ${JSON.stringify(M.instance.options)}`);return M.instance}M.instance=this}get options(){return{version:this.version,apiKey:this.apiKey,channel:this.channel,client:this.client,id:this.id,libraries:this.libraries,language:this.language,region:this.region,mapIds:this.mapIds,nonce:this.nonce,url:this.url}}get failed(){return this.done&&!this.loading&&this.errors.length>=this.retries+1}createUrl(){let e=this.url;return e+=`?callback=${this.CALLBACK}`,this.apiKey&&(e+=`&key=${this.apiKey}`),this.channel&&(e+=`&channel=${this.channel}`),this.client&&(e+=`&client=${this.client}`),0<this.libraries.length&&(e+=`&libraries=${this.libraries.join(",")}`),this.language&&(e+=`&language=${this.language}`),this.region&&(e+=`&region=${this.region}`),this.version&&(e+=`&v=${this.version}`),this.mapIds&&(e+=`&map_ids=${this.mapIds.join(",")}`),e}deleteScript(){let e=document.getElementById(this.id);e&&e.remove()}load(){return this.loadPromise()}loadPromise(){return new Promise((e,t)=>{this.loadCallback(r=>{r?t(r.error):e(window.google)})})}loadCallback(e){this.callbacks.push(e),this.execute()}setScript(){if(document.getElementById(this.id))this.callback();else{var e=this.createUrl(),t=document.createElement("script");t.id=this.id,t.type="text/javascript",t.src=e,t.onerror=this.loadErrorCallback.bind(this),t.defer=!0,t.async=!0,this.nonce&&(t.nonce=this.nonce),document.head.appendChild(t)}}reset(){this.deleteScript(),this.loading=this.done=!1,this.errors=[],this.onerrorEvent=null}resetIfRetryingFailed(){this.failed&&this.reset()}loadErrorCallback(e){this.errors.push(e),this.errors.length<=this.retries?(e=this.errors.length*Math.pow(2,this.errors.length),console.log(`Failed to load Google Maps script, retrying in ${e} ms.`),setTimeout(()=>{this.deleteScript(),this.setScript()},e)):(this.onerrorEvent=e,this.callback())}setCallback(){window.__googleMapsCallback=this.callback.bind(this)}callback(){this.done=!0,this.loading=!1,this.callbacks.forEach(e=>{e(this.onerrorEvent)}),this.callbacks=[]}execute(){this.resetIfRetryingFailed(),this.done?this.callback():window.google&&window.google.maps&&window.google.maps.version?(console.warn("Google Maps already loaded outside @googlemaps/js-api-loader.This may result in undesirable behavior as options and script parameters may not match."),this.callback()):this.loading||(this.loading=!0,this.setCallback(),this.setScript())}}function ze(i){return class extends i.OverlayView{constructor(e){super();let{element:t,...r}=e;this.element=t,this.opts=r,this.opts.map&&this.setMap(this.opts.map)}getPosition(){return this.opts.position?this.opts.position instanceof i.LatLng?this.opts.position:new i.LatLng(this.opts.position):null}getVisible(){if(!this.element)return!1;let e=this.element;return e.style.display!=="none"&&e.style.visibility!=="hidden"&&(e.style.opacity===""||.01<Number(e.style.opacity))}onAdd(){if(this.element){var e=this.getPanes();e&&e.overlayMouseTarget.appendChild(this.element)}}draw(){if(this.element){var e=this.getProjection().fromLatLngToDivPixel(this.getPosition());if(e){this.element.style.position="absolute";let r=this.element.offsetHeight;var t=this.element.offsetWidth;switch(this.opts.anchorPoint){case"TOP_CENTER":t=e.x-t/2,e=e.y;break;case"BOTTOM_CENTER":t=e.x-t/2,e=e.y-r;break;case"LEFT_CENTER":t=e.x,e=e.y-r/2;break;case"RIGHT_CENTER":t=e.x-t,e=e.y-r/2;break;case"TOP_LEFT":t=e.x,e=e.y;break;case"TOP_RIGHT":t=e.x-t,e=e.y;break;case"BOTTOM_LEFT":t=e.x,e=e.y-r;break;case"BOTTOM_RIGHT":t=e.x-t,e=e.y-r;break;default:t=e.x-t/2,e=e.y-r/2}this.element.style.left=t+"px",this.element.style.top=e+"px",this.element.style.transform=`translateX(${this.opts.offsetX||0}px) translateY(${this.opts.offsetY||0}px)`,this.opts.zIndex&&(this.element.style.zIndex=this.opts.zIndex.toString())}}}onRemove(){this.element&&this.element.remove()}setOptions(e){this.opts=e,this.draw()}}}let ne,le="bounds_changed center_changed click dblclick drag dragend dragstart heading_changed idle maptypeid_changed mousemove mouseout mouseover projection_changed resize rightclick tilesloaded tilt_changed zoom_changed".split(" ");var te=w({props:{apiPromise:{type:Promise},apiKey:{type:String,default:""},version:{type:String,default:"weekly"},libraries:{type:Array,default:()=>["places"]},region:{type:String,required:!1},language:{type:String,required:!1},backgroundColor:{type:String,required:!1},center:{type:Object,default:()=>({lat:0,lng:0})},clickableIcons:{type:Boolean,required:!1,default:void 0},controlSize:{type:Number,required:!1},disableDefaultUi:{type:Boolean,required:!1,default:void 0},disableDoubleClickZoom:{type:Boolean,required:!1,default:void 0},draggable:{type:Boolean,required:!1,default:void 0},draggableCursor:{type:String,required:!1},draggingCursor:{type:String,required:!1},fullscreenControl:{type:Boolean,required:!1,default:void 0},fullscreenControlPosition:{type:String,required:!1},gestureHandling:{type:String,required:!1},heading:{type:Number,required:!1},keyboardShortcuts:{type:Boolean,required:!1,default:void 0},mapTypeControl:{type:Boolean,required:!1,default:void 0},mapTypeControlOptions:{type:Object,required:!1},mapTypeId:{type:[Number,String],required:!1},mapId:{type:String,required:!1},maxZoom:{type:Number,required:!1},minZoom:{type:Number,required:!1},noClear:{type:Boolean,required:!1,default:void 0},panControl:{type:Boolean,required:!1,default:void 0},panControlPosition:{type:String,required:!1},restriction:{type:Object,required:!1},rotateControl:{type:Boolean,required:!1,default:void 0},rotateControlPosition:{type:String,required:!1},scaleControl:{type:Boolean,required:!1,default:void 0},scaleControlStyle:{type:Number,required:!1},scrollwheel:{type:Boolean,required:!1,default:void 0},streetView:{type:Object,required:!1},streetViewControl:{type:Boolean,required:!1,default:void 0},streetViewControlPosition:{type:String,required:!1},styles:{type:Array,required:!1},tilt:{type:Number,required:!1},zoom:{type:Number,required:!1},zoomControl:{type:Boolean,required:!1,default:void 0},zoomControlPosition:{type:String,required:!1}},emits:le,setup(i,{emit:e}){let t=m(),r=m(!1),o=m(),s=m(),u=m(!1);T(E,o),T(q,s),T(Ce,u);let n=()=>{const a={...i};Object.keys(a).forEach(h=>{a[h]===void 0&&delete a[h]});var p=h=>{var c;return h?{position:(c=s.value)===null||c===void 0?void 0:c.ControlPosition[h]}:{}};return p={scaleControlOptions:i.scaleControlStyle?{style:i.scaleControlStyle}:{},panControlOptions:p(i.panControlPosition),zoomControlOptions:p(i.zoomControlPosition),rotateControlOptions:p(i.rotateControlPosition),streetViewControlOptions:p(i.streetViewControlPosition),fullscreenControlOptions:p(i.fullscreenControlPosition),disableDefaultUI:i.disableDefaultUi},{...a,...p}},d=x([s,o],([a,p])=>{a&&p&&(a.event.addListenerOnce(p,"tilesloaded",()=>{u.value=!0}),setTimeout(d,0))},{immediate:!0}),l=a=>{s.value=_(a.maps),o.value=_(new a.maps.Map(t.value,n())),a=ze(s.value),s.value[A]=a,le.forEach(p=>{var h;(h=o.value)===null||h===void 0||h.addListener(p,c=>e(p,c))}),r.value=!0,a=Object.keys(i).filter(p=>!"apiPromise apiKey version libraries region language center zoom".split(" ").includes(p)).map(p=>I(i,p)),x([()=>i.center,()=>i.zoom,...a],([p,h],[c,v])=>{var g,f,k;const{center:se,zoom:U,...Se}=n();(g=o.value)===null||g===void 0||g.setOptions(Se),h!==void 0&&h!==v&&((f=o.value)===null||f===void 0||f.setZoom(h)),h=!c||p.lng!==c.lng||p.lat!==c.lat,p&&h&&((k=o.value)===null||k===void 0||k.panTo(p))})};return V(()=>{if(i.apiPromise&&i.apiPromise instanceof Promise)i.apiPromise.then(l);else{try{const{apiKey:a,region:p,version:h,language:c,libraries:v}=i;ne=new M({apiKey:a,region:p,version:h,language:c,libraries:v})}catch(a){console.error(a)}ne.load().then(l)}}),P(()=>{var a;u.value=!1,o.value&&((a=s.value)===null||a===void 0||a.event.clearInstanceListeners(o.value))}),{mapRef:t,ready:r,map:o,api:s,mapTilesLoaded:u}}});function re(i,e){if(e===void 0&&(e={}),e=e.insertAt,i&&typeof document<"u"){var t=document.head||document.getElementsByTagName("head")[0],r=document.createElement("style");r.type="text/css",e==="top"&&t.firstChild?t.insertBefore(r,t.firstChild):t.appendChild(r),r.styleSheet?r.styleSheet.cssText=i:r.appendChild(document.createTextNode(i))}}re(`
.mapdiv[data-v-177d06e3] {
  width: 100%;
  height: 100%;
}
`);let Te=Y();Q("data-v-177d06e3");let Be={ref:"mapRef",class:"mapdiv"};ee();let Ne=Te(i=>(L(),Z("div",null,[C("div",Be,null,512),K(i.$slots,"default",{ready:i.ready,map:i.map,api:i.api,mapTilesLoaded:i.mapTilesLoaded},void 0,!0)])));te.render=Ne;te.__scopeId="data-v-177d06e3";var N=function i(e,t){if(e===t)return!0;if(e&&t&&typeof e=="object"&&typeof t=="object"){if(e.constructor!==t.constructor)return!1;var r;if(Array.isArray(e)){var o=e.length;if(o!=t.length)return!1;for(r=o;r--!==0;)if(!i(e[r],t[r]))return!1;return!0}if(e.constructor===RegExp)return e.source===t.source&&e.flags===t.flags;if(e.valueOf!==Object.prototype.valueOf)return e.valueOf()===t.valueOf();if(e.toString!==Object.prototype.toString)return e.toString()===t.toString();var s=Object.keys(e);if(o=s.length,o!==Object.keys(t).length)return!1;for(r=o;r--!==0;)if(!Object.prototype.hasOwnProperty.call(t,s[r]))return!1;for(r=o;r--!==0;)if(o=s[r],!i(e[o],t[o]))return!1;return!0}return e!==e&&t!==t};let j=(i,e,t,r)=>{const o=m(),s=y(E,m()),u=y(q,m()),n=y(xe,m()),d=$(()=>!!(n.value&&u.value&&(o.value instanceof u.value.Marker||o.value instanceof u.value[A])));return x([s,t],(l,[a,p])=>{var h,c,v;l=!N(t.value,p)||s.value!==a,s.value&&u.value&&l&&(o.value?(o.value.setOptions(t.value),d.value&&((h=n.value)===null||h===void 0||h.removeMarker(o.value),(c=n.value)===null||c===void 0||c.addMarker(o.value))):(o.value=i==="Marker"?_(new u.value[i](t.value)):i===A?_(new u.value[i](t.value)):_(new u.value[i]({...t.value,map:s.value})),d.value?(v=n.value)===null||v===void 0||v.addMarker(o.value):o.value.setMap(s.value),e.forEach(g=>{var f;(f=o.value)===null||f===void 0||f.addListener(g,k=>r(g,k))})))},{immediate:!0}),P(()=>{var l,a;o.value&&((l=u.value)===null||l===void 0||l.event.clearInstanceListeners(o.value),d.value?(a=n.value)===null||a===void 0||a.removeMarker(o.value):o.value.setMap(null))}),o},ae="animation_changed click dblclick rightclick dragstart dragend drag mouseover mousedown mouseout mouseup draggable_changed clickable_changed contextmenu cursor_changed flat_changed rightclick zindex_changed icon_changed position_changed shape_changed title_changed visible_changed".split(" ");var Ze=w({name:"Marker",props:{options:{type:Object,required:!0}},emits:ae,setup(i,{emit:e,expose:t,slots:r}){return i=I(i,"options"),e=j("Marker",ae,i,e),T(we,e),t({marker:e}),()=>{var o;return(o=r.default)===null||o===void 0?void 0:o.call(r)}}});w({name:"Polyline",props:{options:{type:Object,required:!0}},emits:S,setup(i,{emit:e}){return i=I(i,"options"),{polyline:j("Polyline",S,i,e)}},render:()=>null});w({name:"Polygon",props:{options:{type:Object,required:!0}},emits:S,setup(i,{emit:e}){return i=I(i,"options"),{polygon:j("Polygon",S,i,e)}},render:()=>null});let ue=S.concat(["bounds_changed"]);w({name:"Rectangle",props:{options:{type:Object,required:!0}},emits:ue,setup(i,{emit:e}){return i=I(i,"options"),{rectangle:j("Rectangle",ue,i,e)}},render:()=>null});let pe=S.concat(["center_changed","radius_changed"]);w({name:"Circle",props:{options:{type:Object,required:!0}},emits:pe,setup(i,{emit:e}){return i=I(i,"options"),{circle:j("Circle",pe,i,e)}},render:()=>null});var Re=w({props:{position:{type:String,required:!0},index:{type:Number,default:1}},emits:["content:loaded"],setup(i,{emit:e}){let t=m(null),r=y(E,m()),o=y(q,m()),s=y(Ce,m(!1)),u=m(!1),n=x([s,o,t],([a,p,h])=>{p&&a&&h&&(d(i.position),u.value=!0,e("content:loaded"),setTimeout(n,0))},{immediate:!0}),d=a=>{r.value&&o.value&&t.value&&r.value.controls[o.value.ControlPosition[a]].push(t.value)},l=a=>{if(r.value&&o.value){let p=null;a=o.value.ControlPosition[a],r.value.controls[a].forEach((h,c)=>{h===t.value&&(p=c)}),p!==null&&r.value.controls[a].removeAt(p)}};return P(()=>l(i.position)),x(()=>i.position,(a,p)=>{l(p),d(a)}),x(()=>i.index,a=>{a&&t.value&&(t.value.index=i.index)}),{controlRef:t,showContent:u}}});let $e={ref:"controlRef"};Re.render=function(i){return L(),Z(fe,null,[J(`
    v-show must be used instead of v-if otherwise there
    would be no rendered content pushed to the map controls
  `),Ie(C("div",$e,[K(i.$slots,"default")],512),[[Le,i.showContent]])],2112)};let de="closeclick content_changed domready position_changed visible zindex_changed".split(" ");var _e=w({inheritAttrs:!1,props:{options:{type:Object,default:()=>({})}},emits:de,setup(i,{slots:e,emit:t}){let r=m(),o=m(),s=y(E,m()),u=y(q,m()),n=y(we,m()),d,l=$(()=>{var a;return(a=e.default)===null||a===void 0?void 0:a.call(e).some(p=>p.type!==ke)});return V(()=>{x([s,()=>i.options],([,a],[p,h])=>{p=!N(a,h)||s.value!==p,s.value&&u.value&&p&&(r.value?(r.value.setOptions({...a,content:l.value?o.value:a.content}),n.value||r.value.open({map:s.value})):(r.value=_(new u.value.InfoWindow({...a,content:l.value?o.value:a.content})),n.value?d=n.value.addListener("click",()=>{r.value&&r.value.open({map:s.value,anchor:n.value})}):r.value.open({map:s.value}),de.forEach(c=>{var v;(v=r.value)===null||v===void 0||v.addListener(c,g=>t(c,g))})))},{immediate:!0})}),P(()=>{var a;d&&d.remove(),r.value&&((a=u.value)===null||a===void 0||a.event.clearInstanceListeners(r.value),r.value.close())}),{infoWindow:r,infoWindowRef:o,hasSlotContent:l}}});re(`
.info-window-wrapper[data-v-5b373d6e] {
  display: none;
}
.mapdiv .info-window-wrapper[data-v-5b373d6e] {
  display: inline-block;
}
`);let Ae=Y();Q("data-v-5b373d6e");let Fe={key:0,class:"info-window-wrapper"};ee();let Ve=Ae(i=>i.hasSlotContent?(L(),Z("div",Fe,[C("div",ye({ref:"infoWindowRef"},i.$attrs),[K(i.$slots,"default",{},void 0,!0)],16)])):J("v-if",!0));_e.render=Ve;_e.__scopeId="data-v-5b373d6e";function W(i,e,t,r,o,s){if(!(o-r<=t)){var u=r+o>>1;Me(i,e,u,r,o,s%2),W(i,e,t,r,u-1,s+1),W(i,e,t,u+1,o,s+1)}}function Me(i,e,t,r,o,s){for(;o>r;){if(600<o-r){var u=o-r+1,n=t-r+1,d=Math.log(u),l=.5*Math.exp(2*d/3);d=.5*Math.sqrt(d*l*(u-l)/u)*(0>n-u/2?-1:1),Me(i,e,t,Math.max(r,Math.floor(t-n*l/u+d)),Math.min(o,Math.floor(t+(u-n)*l/u+d)),s)}for(u=e[2*t+s],n=r,l=o,z(i,e,r,t),e[2*o+s]>u&&z(i,e,r,o);n<l;){for(z(i,e,n,l),n++,l--;e[2*n+s]<u;)n++;for(;e[2*l+s]>u;)l--}e[2*r+s]===u?z(i,e,r,l):(l++,z(i,e,l,o)),l<=t&&(r=l+1),t<=l&&(o=l-1)}}function z(i,e,t,r){D(i,t,r),D(e,2*t,2*r),D(e,2*t+1,2*r+1)}function D(i,e,t){let r=i[e];i[e]=i[t],i[t]=r}let Ke=i=>i[0],Ue=i=>i[1];class he{constructor(e,t=Ke,r=Ue,o=64,s=Float64Array){this.nodeSize=o,this.points=e;let u=this.ids=new(65536>e.length?Uint16Array:Uint32Array)(e.length);s=this.coords=new s(2*e.length);for(let n=0;n<e.length;n++)u[n]=n,s[2*n]=t(e[n]),s[2*n+1]=r(e[n]);W(u,s,o,0,u.length-1,0)}range(e,t,r,o){{var s=this.ids,u=this.coords,n=this.nodeSize;let l=[0,s.length-1,0],a=[],p,h;for(;l.length;){var d=l.pop();let c=l.pop(),v=l.pop();if(c-v<=n){for(d=v;d<=c;d++)p=u[2*d],h=u[2*d+1],p>=e&&p<=r&&h>=t&&h<=o&&a.push(s[d]);continue}let g=Math.floor((v+c)/2);p=u[2*g],h=u[2*g+1],p>=e&&p<=r&&h>=t&&h<=o&&a.push(s[g]);let f=(d+1)%2;(d===0?e<=p:t<=h)&&(l.push(v),l.push(g-1),l.push(f)),(d===0?r>=p:o>=h)&&(l.push(g+1),l.push(c),l.push(f))}e=a}return e}within(e,t,r){{var o=this.ids,s=this.coords,u=this.nodeSize;let p=[0,o.length-1,0],h=[],c=r*r;for(;p.length;){var n=p.pop();let v=p.pop();var d=p.pop();if(v-d<=u){for(n=d;n<=v;n++){d=s[2*n]-e;var l=s[2*n+1]-t;d=d*d+l*l,d<=c&&h.push(o[n])}continue}l=Math.floor((d+v)/2);let g=s[2*l],f=s[2*l+1];{var a=g-e;let k=f-t;a=a*a+k*k}a<=c&&h.push(o[l]),a=(n+1)%2,(n===0?e-r<=g:t-r<=f)&&(p.push(d),p.push(l-1),p.push(a)),(n===0?e+r>=g:t+r>=f)&&(p.push(l+1),p.push(v),p.push(a))}e=h}return e}}let De={minZoom:0,maxZoom:16,minPoints:2,radius:40,extent:512,nodeSize:64,log:!1,generateId:!1,reduce:null,map:i=>i},F=Math.fround||(i=>e=>(i[0]=+e,i[0]))(new Float32Array(1));class Ge{constructor(e){this.options=B(Object.create(De),e),this.trees=Array(this.options.maxZoom+1)}load(e){let{log:t,minZoom:r,maxZoom:o,nodeSize:s}=this.options;t&&console.time("total time");var u=`prepare ${e.length} points`;t&&console.time(u),this.points=e;let n=[];for(let d=0;d<e.length;d++)e[d].geometry&&n.push(We(e[d],d));for(this.trees[o+1]=new he(n,me,ve,s,Float32Array),t&&console.timeEnd(u),e=o;e>=r;e--)u=+Date.now(),n=this._cluster(n,e),this.trees[e]=new he(n,me,ve,s,Float32Array),t&&console.log("z%d: %d clusters in %dms",e,n.length,+Date.now()-u);return t&&console.timeEnd("total time"),this}getClusters(e,t){let r=((e[0]+180)%360+360)%360-180;var o=Math.max(-90,Math.min(90,e[1])),s=e[2]===180?180:((e[2]+180)%360+360)%360-180;let u=Math.max(-90,Math.min(90,e[3]));if(360<=e[2]-e[0])r=-180,s=180;else if(r>s){var n=this.getClusters([r,o,180,u],t);return o=this.getClusters([-180,o,s,u],t),n.concat(o)}t=this.trees[this._limitZoom(t)],s=t.range(r/360+.5,R(u),s/360+.5,R(o)),o=[];for(n of s)s=t.points[n],o.push(s.numPoints?ce(s):this.points[s.index]);return o}getChildren(e){var t=this._getOriginId(e),r=this._getOriginZoom(e);let o=this.trees[r];if(!o||(t=o.points[t],!t))throw Error("No cluster with the specified id.");t=o.within(t.x,t.y,this.options.radius/(this.options.extent*Math.pow(2,r-1))),r=[];for(let s of t)t=o.points[s],t.parentId===e&&r.push(t.numPoints?ce(t):this.points[t.index]);if(r.length===0)throw Error("No cluster with the specified id.");return r}getLeaves(e,t,r){let o=[];return this._appendLeaves(o,e,t||10,r||0,0),o}getTile(e,t,r){let o=this.trees[this._limitZoom(e)];e=Math.pow(2,e);let{extent:s,radius:u}=this.options,n=u/s,d=(r-n)/e,l=(r+1+n)/e,a={features:[]};return this._addTileFeatures(o.range((t-n)/e,d,(t+1+n)/e,l),o.points,t,r,e,a),t===0&&this._addTileFeatures(o.range(1-n/e,d,1,l),o.points,e,r,e,a),t===e-1&&this._addTileFeatures(o.range(0,d,n/e,l),o.points,-1,r,e,a),a.features.length?a:null}getClusterExpansionZoom(e){let t=this._getOriginZoom(e)-1;for(;t<=this.options.maxZoom&&(e=this.getChildren(e),t++,e.length===1);)e=e[0].properties.cluster_id;return t}_appendLeaves(e,t,r,o,s){t=this.getChildren(t);for(let u of t)if((t=u.properties)&&t.cluster?s=s+t.point_count<=o?s+t.point_count:this._appendLeaves(e,t.cluster_id,r,o,s):s<o?s++:e.push(u),e.length===r)break;return s}_addTileFeatures(e,t,r,o,s,u){for(let l of e){e=t[l];let a=e.numPoints;var n=void 0;let p;var d=void 0;a?(n=Oe(e),p=e.x,d=e.y):(d=this.points[e.index],n=d.properties,p=d.geometry.coordinates[0]/360+.5,d=R(d.geometry.coordinates[1])),n={type:1,geometry:[[Math.round(this.options.extent*(p*s-r)),Math.round(this.options.extent*(d*s-o))]],tags:n};let h;a?h=e.id:this.options.generateId?h=e.index:this.points[e.index].id&&(h=this.points[e.index].id),h!==void 0&&(n.id=h),u.features.push(n)}}_limitZoom(e){return Math.max(this.options.minZoom,Math.min(Math.floor(+e),this.options.maxZoom+1))}_cluster(e,t){let r=[],{radius:o,extent:s,reduce:u,minPoints:n}=this.options,d=o/(s*Math.pow(2,t));for(let c=0;c<e.length;c++){var l=e[c];if(l.zoom<=t)continue;l.zoom=t;let v=this.trees[t+1];var a=v.within(l.x,l.y,d),p=l.numPoints||1;let g=p;for(let f of a){var h=v.points[f];h.zoom>t&&(g+=h.numPoints||1)}if(g>p&&g>=n){h=l.x*p;let f=l.y*p;p=u&&1<p?this._map(l,!0):null;let k=(c<<5)+(t+1)+this.points.length;for(let se of a){if(a=v.points[se],a.zoom<=t)continue;a.zoom=t;let U=a.numPoints||1;h+=a.x*U,f+=a.y*U,a.parentId=k,u&&(p||(p=this._map(l,!0)),u(p,this._map(a)))}l.parentId=k,r.push(He(h/g,f/g,k,g,p))}else if(r.push(l),1<g)for(let f of a)l=v.points[f],l.zoom<=t||(l.zoom=t,r.push(l))}return r}_getOriginId(e){return e-this.points.length>>5}_getOriginZoom(e){return(e-this.points.length)%32}_map(e,t){if(e.numPoints)return t?B({},e.properties):e.properties;e=this.points[e.index].properties;let r=this.options.map(e);return t&&r===e?B({},r):r}}function He(i,e,t,r,o){return{x:F(i),y:F(e),zoom:1/0,id:t,parentId:-1,numPoints:r,properties:o}}function We(i,e){let[t,r]=i.geometry.coordinates;return{x:F(t/360+.5),y:F(R(r)),zoom:1/0,index:e,parentId:-1}}function ce(i){var e=i.id,t=Oe(i);return{type:"Feature",id:e,properties:t,geometry:{type:"Point",coordinates:[360*(i.x-.5),360*Math.atan(Math.exp((180-360*i.y)*Math.PI/180))/Math.PI-90]}}}function Oe(i){let e=i.numPoints,t=1e4<=e?`${Math.round(e/1e3)}k`:1e3<=e?`${Math.round(e/100)/10}k`:e;return B(B({},i.properties),{cluster:!0,cluster_id:i.id,point_count:e,point_count_abbreviated:t})}function R(i){return i=Math.sin(i*Math.PI/180),i=.5-.25*Math.log((1+i)/(1-i))/Math.PI,0>i?0:1<i?1:i}function B(i,e){for(let t in e)i[t]=e[t];return i}function me(i){return i.x}function ve(i){return i.y}class X{constructor({markers:e,position:t}){this.markers=e,t&&(this._position=t instanceof google.maps.LatLng?t:new google.maps.LatLng(t))}get bounds(){if(this.markers.length!==0||this._position)return this.markers.reduce((e,t)=>e.extend(t.getPosition()),new google.maps.LatLngBounds(this._position,this._position))}get position(){return this._position||this.bounds.getCenter()}get count(){return this.markers.filter(e=>e.getVisible()).length}push(e){this.markers.push(e)}delete(){this.marker&&(this.marker.setMap(null),delete this.marker),this.markers.length=0}}class Xe{constructor({maxZoom:e=16}){this.maxZoom=e}noop({markers:e}){return Je(e)}}let Je=i=>i.map(e=>new X({position:e.getPosition(),markers:[e]}));class Ye extends Xe{constructor(e){var{maxZoom:t,radius:r=60}=e,o=["maxZoom","radius"],s={},u;for(u in e)Object.prototype.hasOwnProperty.call(e,u)&&0>o.indexOf(u)&&(s[u]=e[u]);if(e!=null&&typeof Object.getOwnPropertySymbols=="function"){var n=0;for(u=Object.getOwnPropertySymbols(e);n<u.length;n++)0>o.indexOf(u[n])&&Object.prototype.propertyIsEnumerable.call(e,u[n])&&(s[u[n]]=e[u[n]])}super({maxZoom:t}),this.superCluster=new Ge(Object.assign({maxZoom:this.maxZoom,radius:r},s)),this.state={zoom:null}}calculate(e){let t=!1;if(!N(e.markers,this.markers)){t=!0,this.markers=[...e.markers];var r=this.markers.map(o=>({type:"Feature",geometry:{type:"Point",coordinates:[o.getPosition().lng(),o.getPosition().lat()]},properties:{marker:o}}));this.superCluster.load(r)}return r={zoom:e.map.getZoom()},t||this.state.zoom>this.maxZoom&&r.zoom>this.maxZoom||(t=t||!N(this.state,r)),this.state=r,t&&(this.clusters=this.cluster(e)),{clusters:this.clusters,changed:t}}cluster({map:e}){return this.superCluster.getClusters([-180,-90,180,90],Math.round(e.getZoom())).map(this.transformCluster.bind(this))}transformCluster({geometry:{coordinates:[e,t]},properties:r}){return r.cluster?new X({markers:this.superCluster.getLeaves(r.cluster_id,1/0).map(o=>o.properties.marker),position:new google.maps.LatLng({lat:t,lng:e})}):(e=r.marker,new X({markers:[e],position:e.getPosition()}))}}class Qe{constructor(e,t){this.markers={sum:e.length},e=t.map(o=>o.count);let r=e.reduce((o,s)=>o+s,0);this.clusters={count:t.length,markers:{mean:r/t.length,sum:r,min:Math.min(...e),max:Math.max(...e)}}}}class et{render({count:e,position:t},r){return r=window.btoa(`
  <svg fill="${e>Math.max(10,r.clusters.markers.mean)?"#ff0000":"#0000ff"}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
    <circle cx="120" cy="120" opacity=".6" r="70" />
    <circle cx="120" cy="120" opacity=".3" r="90" />
    <circle cx="120" cy="120" opacity=".2" r="110" />
  </svg>`),new google.maps.Marker({position:t,icon:{url:`data:image/svg+xml;base64,${r}`,scaledSize:new google.maps.Size(45,45)},label:{text:String(e),color:"rgba(255,255,255,0.9)",fontSize:"12px"},title:`Cluster of ${e} markers`,zIndex:Number(google.maps.Marker.MAX_ZINDEX)+e})}}class oe{constructor(){var e=oe,t=google.maps.OverlayView;for(let r in t.prototype)e.prototype[r]=t.prototype[r]}}var b,ie=b||(b={});ie.CLUSTERING_BEGIN="clusteringbegin";ie.CLUSTERING_END="clusteringend";ie.CLUSTER_CLICK="click";let tt=(i,e,t)=>{t.fitBounds(e.bounds)};class rt extends oe{constructor({map:e,markers:t=[],algorithm:r=new Ye({}),renderer:o=new et,onClusterClick:s=tt}){super(),this.markers=[...t],this.clusters=[],this.algorithm=r,this.renderer=o,this.onClusterClick=s,e&&this.setMap(e)}addMarker(e,t){this.markers.includes(e)||(this.markers.push(e),t||this.render())}addMarkers(e,t){e.forEach(r=>{this.addMarker(r,!0)}),t||this.render()}removeMarker(e,t){let r=this.markers.indexOf(e);return r===-1?!1:(e.setMap(null),this.markers.splice(r,1),t||this.render(),!0)}removeMarkers(e,t){let r=!1;return e.forEach(o=>{r=this.removeMarker(o,!0)||r}),r&&!t&&this.render(),r}clearMarkers(e){this.markers.length=0,e||this.render()}render(){let e=this.getMap();if(e instanceof google.maps.Map&&this.getProjection()){google.maps.event.trigger(this,b.CLUSTERING_BEGIN,this);let{clusters:t,changed:r}=this.algorithm.calculate({markers:this.markers,map:e,mapCanvasProjection:this.getProjection()});(r||r==null)&&(this.reset(),this.clusters=t,this.renderClusters()),google.maps.event.trigger(this,b.CLUSTERING_END,this)}}onAdd(){this.idleListener=this.getMap().addListener("idle",this.render.bind(this)),this.render()}onRemove(){google.maps.event.removeListener(this.idleListener),this.reset()}reset(){this.markers.forEach(e=>e.setMap(null)),this.clusters.forEach(e=>e.delete()),this.clusters=[]}renderClusters(){let e=new Qe(this.markers,this.clusters),t=this.getMap();this.clusters.forEach(r=>{r.markers.length===1?r.marker=r.markers[0]:(r.marker=this.renderer.render(r,e),this.onClusterClick&&r.marker.addListener("click",o=>{google.maps.event.trigger(this,b.CLUSTER_CLICK,r),this.onClusterClick(o,r,t)})),r.marker.setMap(t)})}}let ge=Object.values(b);w({name:"MarkerCluster",props:{options:{type:Object,default:()=>({})}},emits:ge,setup(i,{emit:e,expose:t,slots:r}){let o=m(),s=y(E,m()),u=y(q,m());return T(xe,o),x(s,()=>{s.value&&(o.value=_(new rt({map:s.value,...i.options})),ge.forEach(n=>{var d;(d=o.value)===null||d===void 0||d.addListener(n,l=>e(n,l))}))},{immediate:!0}),P(()=>{var n;o.value&&((n=u.value)===null||n===void 0||n.event.clearInstanceListeners(o.value),o.value.clearMarkers(),o.value.setMap(null))}),t({markerCluster:o}),()=>{var n;return(n=r.default)===null||n===void 0?void 0:n.call(r)}}});var be=w({inheritAttrs:!1,props:{options:{type:Object,required:!0}},setup(i,{slots:e,emit:t}){let r=m(),o=m(),s=$(()=>{var n;return(n=e.default)===null||n===void 0?void 0:n.call(e).some(d=>d.type!==ke)}),u=$(()=>({...i.options,element:r.value}));return V(()=>{o=j(A,[],u,t)}),{customMarkerRef:r,customMarker:o,hasSlotContent:s}}});re(`
.custom-marker-wrapper[data-v-b9d5ec8a] {
  display: none;
}
.mapdiv .custom-marker-wrapper[data-v-b9d5ec8a] {
  display: inline-block;
}
`);let ot=Y();Q("data-v-b9d5ec8a");let it={key:0,class:"custom-marker-wrapper"};ee();let st=ot(i=>i.hasSlotContent?(L(),Z("div",it,[C("div",ye({ref:"customMarkerRef",style:{cursor:i.$attrs.onClick?"pointer":void 0}},i.$attrs),[K(i.$slots,"default",{},void 0,!0)],16)])):J("v-if",!0));be.render=st;be.__scopeId="data-v-b9d5ec8a";w({name:"HeatmapLayer",props:{options:{type:Object,default:()=>({})}},setup(i){let e=m(),t=y(E,m()),r=y(q,m());return x([t,()=>i.options],([,o],[s,u])=>{var n;if(s=!N(o,u)||t.value!==s,t.value&&r.value&&s){if(o=structuredClone(o),o.data&&!(o.data instanceof r.value.MVCArray)){let d=r.value.LatLng;o.data=(n=o.data)===null||n===void 0?void 0:n.map(l=>l instanceof d||"location"in l&&(l.location instanceof d||l.location===null)?l:"location"in l?{...l,location:new d(l.location)}:new d(l))}e.value?e.value.setOptions(o):e.value=_(new r.value.visualization.HeatmapLayer({...o,map:t.value}))}},{immediate:!0}),P(()=>{e.value&&e.value.setMap(null)}),{heatmapLayer:e}},render:()=>null});const nt={__name:"GoogleMap",setup(i){const e=m({lat:1.3521,lng:103.8198}),t=m(12),r=m("AIzaSyDBvrDb4Nvv4zggmkmxQ5vGzafgqFVoqTU");return(o,s)=>(L(),Z(H(te),{"api-key":r.value,style:{width:"100%",height:"600px"},center:e.value,zoom:t.value},{default:G(()=>[C(H(Ze),{options:{position:e.value}},null,8,["options"])]),_:1},8,["api-key","center","zoom"]))}},lt=O("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machine Map ",-1),at={class:"overflow-hidden bg-white shadow sm:rounded-md"},ut={class:""},pt={class:"mt-2 border-2 items-center justify-center py-3 pl-3 pr-4 text-sm space-y-3"},dt=O("span",{class:"font-medium px-2 py-4"}," Vending Machine Map ",-1),ft={__name:"Index",props:{vends:[Object,Array]},setup(i){const e=i;return V(()=>{console.log(e.vends)}),(t,r)=>(L(),Ee(fe,null,[C(H(qe),{title:"Vending Machine Map"}),C(Pe,null,{header:G(()=>[lt]),default:G(()=>[O("div",at,[O("div",ut,[O("div",pt,[dt,O("div",null,[C(nt)])])])])]),_:1})],64))}};export{ft as default};
