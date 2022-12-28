import{_ as F}from"./Authenticated.5e7a70a2.js";import{_ as u}from"./Button.a5e4aba5.js";import K from"./Form.472ec256.js";import{_ as T,r as D,T as P,a as A,b as E,c as b}from"./TableHeadSort.2033dae2.js";import{_ as H}from"./MultiSelect.aa3a34c6.js";import{i as m,j as U,o as i,g as x,a as t,b as d,w as o,F as z,H as O,d as e,t as f,m as R,p as w,c as B,f as g,J as k}from"./app.af89aff7.js";import{r as Z}from"./PlusIcon.705753f4.js";import{r as J}from"./BackspaceIcon.0a13fd7b.js";import{r as q}from"./PencilSquareIcon.38824dfd.js";import{r as G}from"./TrashIcon.88a748a8.js";import"./open-closed.b6538a35.js";import"./use-resolve-button-type.bdc1c47a.js";import"./RectangleStackIcon.3053cb6c.js";import"./FormInput.23b5513a.js";import"./Modal.2a551b42.js";import"./ArrowUturnLeftIcon.fdb91ce7.js";import"./CheckCircleIcon.6a17fd85.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.768f14c7.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Zone) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=g(" Name "),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},ae={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),pe={class:"font-medium"},xe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=g(" # "),Ce=g(" Name "),$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},ze=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Se={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ne=[Ve],We={__name:"Index",props:{zones:Object},setup(n){const a=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),_=m(),h=m(""),v=m([]);U(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=v.value[0]});function S(){h.value="create",_.value=null,c.value=!0}function V(r){!confirm("Are you sure to delete "+r.name+"?")||k.Inertia.delete("/zones/"+r.id)}function N(r){h.value="update",_.value=r,c.value=!0}function y(){k.Inertia.get("/zones",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){k.Inertia.get("/zones")}function L(r){a.value.sortKey=r,a.value.sortBy=!a.value.sortBy,y()}function M(){c.value=!1}return(r,s)=>(i(),x(z,null,[t(d(O),{title:"Zone"}),t(F,null,{header:o(()=>[Q]),default:o(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>S())},{default:o(()=>[t(d(Z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(T,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>a.value.name=l)},{default:o(()=>[se]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ne,[e("div",ae,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(d(D),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>I())},{default:o(()=>[t(d(J),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ce,e("span",ue,f((C=n.zones.meta.from)!=null?C:0),1),me,e("span",fe,f(($=n.zones.meta.to)!=null?$:0),1),ge,e("span",pe,f(n.zones.meta.total),1),xe]),t(H,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>a.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",he,[e("div",ve,[e("table",ye,[e("thead",be,[e("tr",we,[t(P,null,{default:o(()=>[ke]),_:1}),t(A,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[5]||(s[5]=l=>L("name"))},{default:o(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",$e,[(i(!0),x(z,null,R(n.zones.data,(l,p)=>(i(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:p,totalLength:n.zones.length,inputClass:"text-center"},{default:o(()=>[g(f(n.zones.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:n.zones.length,inputClass:"text-left"},{default:o(()=>[g(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:n.zones.length,inputClass:"text-center"},{default:o(()=>[e("div",Pe,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>N(l)},{default:o(()=>[t(d(q),{class:"w-4 h-4"}),ze]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>V(l)},{default:o(()=>[t(d(G),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.zones.data.length?w("",!0):(i(),x("tr",Se,Ne))])]),n.zones.data.length?(i(),B(E,{key:0,links:n.zones.links,meta:n.zones.meta},null,8,["links","meta"])):w("",!0)])])])]),c.value?(i(),B(K,{key:0,zone:_.value,type:h.value,showModal:c.value,onModalClose:M},null,8,["zone","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};
