import{_ as K}from"./Authenticated.e95bf50b.js";import{_ as v}from"./Button.436862b4.js";import T from"./Form.8b950448.js";import{_ as U,r as H,T as c,a as A,b as u}from"./TableData.717a1486.js";import{_ as D}from"./MultiSelect.7c093a7b.js";import{_ as O}from"./TableHeadSort.acbdacac.js";import{i as h,k as Q,j as J,o as m,g as b,a as t,b as f,w as s,F as B,H as q,d as e,t as i,f as o,m as z,p as C,c as $,l as G,J as I}from"./app.a362a3aa.js";import W from"./ExchangeRate.978c28c7.js";import{r as X}from"./PlusIcon.2d16c04b.js";import{r as Y}from"./BackspaceIcon.72ff763c.js";import{r as Z}from"./PlusCircleIcon.501d203f.js";import{r as ee}from"./PencilSquareIcon.7283c01e.js";import"./open-closed.a45ada34.js";import"./use-resolve-button-type.9df7e668.js";import"./RectangleStackIcon.fcda69be.js";import"./FormInput.f3be7155.js";import"./Modal.b22eed67.js";import"./ArrowUturnLeftIcon.dcd2995f.js";import"./CheckCircleIcon.c74288ce.js";import"./_plugin-vue_export-helper.cdc0426e.js";const te=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Country & Currency) ",-1),se={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ne={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ae={class:"flex justify-end"},oe=e("span",null," Create ",-1),le={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re=o(" Name "),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ue={class:"flex space-x-1"},ce=e("span",null," Search ",-1),me=e("span",null," Reset ",-1),fe={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},he=e("span",null,"Showing",-1),_e={class:"font-medium"},xe=e("span",null,"to",-1),pe={class:"font-medium"},ve=e("span",null,"of",-1),ye={class:"font-medium"},be=e("span",null,"results",-1),Ce={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},$e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Le={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Be=o(" # "),Ie=o(" Name "),Se=o(" Code "),Me=o(" Currency "),Ve=o(" Symbol "),Ne=o(" Phone Code "),Ee=o(" Latest Rate "),Re=e("br",null,null,-1),je={class:"bg-white"},Fe={class:"flex justify-center space-x-1"},Ke=e("span",null," Rate ",-1),Te=e("span",null," Edit ",-1),Ue={key:0},He=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ae=[He],dt={__name:"Index",props:{countries:Object},setup(a){const r=h({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),_=h(!1),y=h(!1),x=h(),p=h(""),w=h([]),S=Q(()=>G().props.value.auth.user);J(()=>{w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=w.value[0]});function M(){p.value="create",x.value=null,_.value=!0}function V(g){p.value="update",x.value=g,_.value=!0}function N(g){p.value="update",x.value=g,y.value=!0}function k(){I.Inertia.get("/countries",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){I.Inertia.get("/countries")}function R(g){r.value.sortKey=g,r.value.sortBy=!r.value.sortBy,k()}function j(){_.value=!1}return(g,l)=>(m(),b(B,null,[t(f(q),{title:"Country"}),t(K,null,{header:s(()=>[te]),default:s(()=>{var L,P;return[e("div",se,[e("div",ne,[e("div",ae,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=n=>M())},{default:s(()=>[t(f(X),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1})]),e("div",le,[t(U,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":l[1]||(l[1]=n=>r.value.name=n)},{default:s(()=>[re]),_:1},8,["modelValue"])]),e("div",ie,[e("div",de,[e("div",ue,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[2]||(l[2]=n=>k())},{default:s(()=>[t(f(H),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=n=>E())},{default:s(()=>[t(f(Y),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),e("div",fe,[e("p",ge,[he,e("span",_e,i((L=a.countries.meta.from)!=null?L:0),1),xe,e("span",pe,i((P=a.countries.meta.to)!=null?P:0),1),ve,e("span",ye,i(a.countries.meta.total),1),be]),t(D,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":l[4]||(l[4]=n=>r.value.numberPerPage=n),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"])])])]),e("div",Ce,[e("div",we,[e("div",ke,[e("table",$e,[e("thead",Le,[e("tr",Pe,[t(c,null,{default:s(()=>[Be]),_:1}),t(O,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:l[5]||(l[5]=n=>R("name"))},{default:s(()=>[Ie]),_:1},8,["sortKey","sortBy"]),t(c,null,{default:s(()=>[Se]),_:1}),t(c,null,{default:s(()=>[Me]),_:1}),t(c,null,{default:s(()=>[Ve]),_:1}),t(c,null,{default:s(()=>[Ne]),_:1}),t(c,null,{default:s(()=>[Ee,Re,o(" (Base: "+i(f(S).profile.base_currency.currency_name)+") ",1)]),_:1}),t(c)])]),e("tbody",je,[(m(!0),b(B,null,z(a.countries.data,(n,d)=>(m(),b("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(a.countries.meta.from+d),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(n.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(n.currency_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(n.currency_symbol),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[o(i(n.phone_code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-right"},{default:s(()=>[o(i(n.latestQuoteExchangeRate?n.latestQuoteExchangeRate.rate:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:d,totalLength:a.countries.length,inputClass:"text-center"},{default:s(()=>[e("div",Fe,[t(v,{type:"button",class:"bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1",onClick:F=>N(n)},{default:s(()=>[t(f(Z),{class:"h-4 w-4","aria-hidden":"true"}),Ke]),_:2},1032,["onClick"]),t(v,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>V(n)},{default:s(()=>[t(f(ee),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.countries.data.length?C("",!0):(m(),b("tr",Ue,Ae))])]),a.countries.data.length?(m(),$(A,{key:0,links:a.countries.links,meta:a.countries.meta},null,8,["links","meta"])):C("",!0)])])])]),_.value?(m(),$(T,{key:0,country:x.value,type:p.value,showModal:_.value,onModalClose:j},null,8,["country","type","showModal"])):C("",!0),y.value?(m(),$(W,{key:1,country:x.value,type:p.value,showModal:y.value,onModalClose:l[6]||(l[6]=n=>y.value=!1)},null,8,["country","type","showModal"])):C("",!0)]}),_:1})],64))}};export{dt as default};
