import{_ as K}from"./Authenticated.a9654884.js";import{_ as v}from"./Button.bf7c4f05.js";import T from"./Form.4f458173.js";import{_ as U,r as H,a as z,T as m,b as A,c as D,d as u}from"./TableHeadSort.059d48eb.js";import{_ as O}from"./MultiSelect.5946c540.js";import{o as c,g as y,d as e,i as h,k as Q,j as J,a as t,b as g,w as s,F as B,H as q,t as i,f as l,m as G,p as w,c as $,l as W,J as I}from"./app.dc45475b.js";import X from"./ExchangeRate.67fe568b.js";import{r as Y}from"./PlusIcon.8da8a5c6.js";import{r as Z}from"./PencilSquareIcon.f275b11f.js";import"./open-closed.21e6db5b.js";import"./use-resolve-button-type.3a144016.js";import"./RectangleStackIcon.b52de852.js";import"./FormInput.c7ffb148.js";import"./Modal.cd08ed63.js";import"./ArrowUturnLeftIcon.f39898e6.js";import"./CheckCircleIcon.1843db13.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.80d2bc43.js";function ee(a,r){return c(),y("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z","clip-rule":"evenodd"})])}const te=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Country & Currency) ",-1),se={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ne={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ae={class:"flex justify-end"},le=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re=l(" Name "),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ue={class:"flex space-x-1"},ce=e("span",null," Search ",-1),me=e("span",null," Reset ",-1),ge={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},he=e("span",null,"Showing",-1),_e={class:"font-medium"},xe=e("span",null,"to",-1),pe={class:"font-medium"},ve=e("span",null,"of",-1),ye={class:"font-medium"},be=e("span",null,"results",-1),we={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},$e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Le={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Be=l(" # "),Ie=l(" Name "),Me=l(" Code "),Se=l(" Currency "),Ve=l(" Symbol "),Ne=l(" Phone Code "),Ee=l(" Latest Rate "),Re=e("br",null,null,-1),je={class:"bg-white"},Fe={class:"flex justify-center space-x-1"},Ke=e("span",null," Rate ",-1),Te=e("span",null," Edit ",-1),Ue={key:0},He=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ze=[He],rt={__name:"Index",props:{countries:Object},setup(a){const r=h({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),_=h(!1),b=h(!1),x=h(),p=h(""),C=h([]),M=Q(()=>W().props.value.auth.user);J(()=>{C.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=C.value[0]});function S(){p.value="create",x.value=null,_.value=!0}function V(f){p.value="update",x.value=f,_.value=!0}function N(f){p.value="update",x.value=f,b.value=!0}function k(){I.Inertia.get("/countries",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){I.Inertia.get("/countries")}function R(f){r.value.sortKey=f,r.value.sortBy=!r.value.sortBy,k()}function j(){_.value=!1}return(f,o)=>(c(),y(B,null,[t(g(q),{title:"Country"}),t(K,null,{header:s(()=>[te]),default:s(()=>{var L,P;return[e("div",se,[e("div",ne,[e("div",ae,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=n=>S())},{default:s(()=>[t(g(Y),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",oe,[t(U,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":o[1]||(o[1]=n=>r.value.name=n)},{default:s(()=>[re]),_:1},8,["modelValue"])]),e("div",ie,[e("div",de,[e("div",ue,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=n=>k())},{default:s(()=>[t(g(H),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=n=>E())},{default:s(()=>[t(g(z),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),e("div",ge,[e("p",fe,[he,e("span",_e,i((L=a.countries.meta.from)!=null?L:0),1),xe,e("span",pe,i((P=a.countries.meta.to)!=null?P:0),1),ve,e("span",ye,i(a.countries.meta.total),1),be]),t(O,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=n=>r.value.numberPerPage=n),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"])])])]),e("div",we,[e("div",Ce,[e("div",ke,[e("table",$e,[e("thead",Le,[e("tr",Pe,[t(m,null,{default:s(()=>[Be]),_:1}),t(A,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:o[5]||(o[5]=n=>R("name"))},{default:s(()=>[Ie]),_:1},8,["sortKey","sortBy"]),t(m,null,{default:s(()=>[Me]),_:1}),t(m,null,{default:s(()=>[Se]),_:1}),t(m,null,{default:s(()=>[Ve]),_:1}),t(m,null,{default:s(()=>[Ne]),_:1}),t(m,null,{default:s(()=>[Ee,Re,l(" (Base: "+i(g(M).profile.base_currency.currency_name)+") ",1)]),_:1}),t(m)])]),e("tbody",je,[(c(!0),y(B,null,G(a.countries.data,(n,d)=>(c(),y("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(a.countries.meta.from+d),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(n.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(n.currency_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(n.currency_symbol),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[l(i(n.phone_code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-right"},{default:s(()=>[l(i(n.latestQuoteExchangeRate?n.latestQuoteExchangeRate.rate:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[e("div",Fe,[t(v,{type:"button",class:"bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1",onClick:F=>N(n)},{default:s(()=>[t(g(ee),{class:"h-4 w-4","aria-hidden":"true"}),Ke]),_:2},1032,["onClick"]),t(v,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>V(n)},{default:s(()=>[t(g(Z),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.countries.data.length?w("",!0):(c(),y("tr",Ue,ze))])]),a.countries.data.length?(c(),$(D,{key:0,links:a.countries.links,meta:a.countries.meta},null,8,["links","meta"])):w("",!0)])])])]),_.value?(c(),$(T,{key:0,country:x.value,type:p.value,showModal:_.value,onModalClose:j},null,8,["country","type","showModal"])):w("",!0),b.value?(c(),$(X,{key:1,country:x.value,type:p.value,showModal:b.value,onModalClose:o[6]||(o[6]=n=>b.value=!1)},null,8,["country","type","showModal"])):w("",!0)]}),_:1})],64))}};export{rt as default};
