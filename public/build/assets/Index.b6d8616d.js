import{g as y,K as G,h as j,f as c,a as l,u as r,w as n,F as U,o as i,Z as A,b as t,M as _,d as u,c as C,l as p,t as g,k as E,O as w}from"./app.59b044b8.js";import{_ as M}from"./Authenticated.69f7f963.js";import{_ as h}from"./Button.23f7c4aa.js";import{_ as R}from"./Paginator.0c9a8688.js";import{_ as b,r as Z}from"./SearchInput.9631f5a9.js";import{_ as v}from"./MultiSelect.b191c470.js";import{_ as B,a as V}from"./TableData.98598b1e.js";import{_ as q}from"./TableHeadSort.efd96308.js";import{r as z}from"./PlusIcon.fd75ca6b.js";import{r as H}from"./BackspaceIcon.2eb46dcd.js";import{r as J}from"./PencilSquareIcon.12b99ad6.js";import{r as Q}from"./TrashIcon.1a833294.js";import"./open-closed.5c100063.js";import"./use-resolve-button-type.0681d336.js";import"./RectangleStackIcon.5e6bda85.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.80550338.js";const W=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vend & Criteria Bindings ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ee={class:"flex justify-end"},te=t("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),le=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ne={key:2},ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),de={key:3},re=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ie={key:4},ue=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),me={key:5},ce=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),fe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ge={class:"mt-3"},ve={class:"flex space-x-1"},xe=t("span",null," Search ",-1),ye=t("span",null," Reset ",-1),_e={class:"flex flex-col space-y-2"},he={class:"text-sm text-gray-700 leading-5 flex space-x-1"},be=t("span",null,"Showing",-1),Ve={class:"font-medium"},ke=t("span",null,"to",-1),Ce={class:"font-medium"},we=t("span",null,"of",-1),Be={class:"font-medium"},$e=t("span",null,"results",-1),Pe={class:"mt-6 flex flex-col"},Se={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ne={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ue={class:"bg-gray-100"},Ie={class:"divide-x divide-gray-200"},Le={class:"bg-white"},Oe={class:"flex justify-center space-x-1"},De=t("span",null," Edit ",-1),Te=t("span",null," Delete ",-1),Fe={key:0},Ge=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),je=[Ge],st={__name:"Index",props:{vendCriteriaBindings:Object},setup(a){const s=y({codes:"",channel_codes:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],locationType:"",operator:"",is_binded_customer:"",sortKey:"",sortBy:!0,numberPerPage:100,visited:!0}),$=y(!1),P=y(),S=y(""),k=y([]),f=G().props.auth.permissions;j(()=>{k.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=k.value[0]});function I(){S.value="create",P.value=null,$.value=!0}function L(d){!confirm("Are you sure to delete "+d.name+"?")||w.delete("/location-types/"+d.id)}function O(d){S.value="update",P.value=d,$.value=!0}function m(){w.get("/location-types",{...s.value,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function D(){w.get("/location-types")}function T(d){s.value.sortKey=d,s.value.sortBy=!s.value.sortBy,m()}return(d,e)=>(i(),c(U,null,[l(r(A),{title:"Location Type"}),l(M,null,{header:n(()=>[W]),default:n(()=>{var N,K;return[t("div",X,[t("div",Y,[t("div",ee,[l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[0]||(e[0]=o=>I())},{default:n(()=>[l(r(z),{class:"h-4 w-4","aria-hidden":"true"}),te]),_:1})]),t("div",oe,[l(b,{placeholderStr:"Vend ID",modelValue:s.value.codes,"onUpdate:modelValue":e[1]||(e[1]=o=>s.value.codes=o),onKeyup:e[2]||(e[2]=_(o=>m(),["enter"]))},{default:n(()=>[u(" Vend ID "),se]),_:1},8,["modelValue"]),l(b,{placeholderStr:"Channel ID",modelValue:s.value.channel_codes,"onUpdate:modelValue":e[3]||(e[3]=o=>s.value.channel_codes=o),onKeyup:e[4]||(e[4]=_(o=>m(),["enter"]))},{default:n(()=>[u(" Channel ID "),le]),_:1},8,["modelValue"]),l(b,{placeholderStr:"Serial Num",modelValue:s.value.serialNum,"onUpdate:modelValue":e[5]||(e[5]=o=>s.value.serialNum=o),onKeyup:e[6]||(e[6]=_(o=>m(),["enter"]))},{default:n(()=>[u(" Serial Num ")]),_:1},8,["modelValue"]),r(f).includes("admin-access vends")?(i(),C(b,{key:0,placeholderStr:"Cust ID",modelValue:s.value.customer_code,"onUpdate:modelValue":e[7]||(e[7]=o=>s.value.customer_code=o),onKeyup:e[8]||(e[8]=_(o=>m(),["enter"]))},{default:n(()=>[u(" Cust ID ")]),_:1},8,["modelValue"])):p("",!0),r(f).includes("admin-access vends")?(i(),C(b,{key:1,placeholderStr:"Cust Name",modelValue:s.value.customer_name,"onUpdate:modelValue":e[9]||(e[9]=o=>s.value.customer_name=o),onKeyup:e[10]||(e[10]=_(o=>m(),["enter"]))},{default:n(()=>[u(" Cust Name ")]),_:1},8,["modelValue"])):p("",!0),r(f).includes("admin-access vends")?(i(),c("div",ne,[ae,l(v,{modelValue:s.value.categories,"onUpdate:modelValue":e[11]||(e[11]=o=>s.value.categories=o),options:d.categoryOptions,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),r(f).includes("admin-access vends")?(i(),c("div",de,[re,l(v,{modelValue:s.value.categoryGroups,"onUpdate:modelValue":e[12]||(e[12]=o=>s.value.categoryGroups=o),options:d.categoryGroupOptions,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),r(f).includes("admin-access vends")?(i(),c("div",ie,[ue,l(v,{modelValue:s.value.is_binded_customer,"onUpdate:modelValue":e[13]||(e[13]=o=>s.value.is_binded_customer=o),options:d.booleanOptions,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),r(f).includes("admin-access vends")?(i(),c("div",me,[ce,l(v,{modelValue:s.value.operator,"onUpdate:modelValue":e[14]||(e[14]=o=>s.value.operator=o),options:d.operatorOptions,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):p("",!0),t("div",null,[pe,l(v,{modelValue:s.value.locationType,"onUpdate:modelValue":e[15]||(e[15]=o=>s.value.locationType=o),options:d.locationTypeOptions,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",fe,[t("div",ge,[t("div",ve,[l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[16]||(e[16]=o=>m())},{default:n(()=>[l(r(Z),{class:"h-4 w-4","aria-hidden":"true"}),xe]),_:1}),l(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:e[17]||(e[17]=o=>D())},{default:n(()=>[l(r(H),{class:"h-4 w-4","aria-hidden":"true"}),ye]),_:1})])]),t("div",_e,[t("p",he,[be,t("span",Ve,g((N=a.vendCriteriaBindings.meta.from)!=null?N:0),1),ke,t("span",Ce,g((K=a.vendCriteriaBindings.meta.to)!=null?K:0),1),we,t("span",Be,g(a.vendCriteriaBindings.meta.total),1),$e]),l(v,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":e[18]||(e[18]=o=>s.value.numberPerPage=o),options:k.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:m},null,8,["modelValue","options"])])])]),t("div",Pe,[t("div",Se,[t("div",Ne,[t("table",Ke,[t("thead",Ue,[t("tr",Ie,[l(B,null,{default:n(()=>[u(" # ")]),_:1}),l(q,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:e[19]||(e[19]=o=>T("code"))},{default:n(()=>[u(" Code ")]),_:1},8,["sortKey","sortBy"]),l(B,null,{default:n(()=>[u(" Name ")]),_:1}),l(B)])]),t("tbody",Le,[(i(!0),c(U,null,E(a.vendCriteriaBindings.data,(o,x)=>(i(),c("tr",{key:o.id,class:"divide-x divide-gray-200"},[l(V,{currentIndex:x,totalLength:a.vendCriteriaBindings.length,inputClass:"text-center"},{default:n(()=>[u(g(a.vendCriteriaBindings.meta.from+x),1)]),_:2},1032,["currentIndex","totalLength"]),l(V,{currentIndex:x,totalLength:a.vendCriteriaBindings.length,inputClass:"text-center"},{default:n(()=>[u(g(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),l(V,{currentIndex:x,totalLength:a.vendCriteriaBindings.length,inputClass:"text-left"},{default:n(()=>[u(g(o.full_name),1)]),_:2},1032,["currentIndex","totalLength"]),l(V,{currentIndex:x,totalLength:a.vendCriteriaBindings.length,inputClass:"text-center"},{default:n(()=>[t("div",Oe,[l(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>O(o)},{default:n(()=>[l(r(J),{class:"w-4 h-4"}),De]),_:2},1032,["onClick"]),l(h,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>L(o)},{default:n(()=>[l(r(Q),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.vendCriteriaBindings.data.length?p("",!0):(i(),c("tr",Fe,je))])]),a.vendCriteriaBindings.data.length?(i(),C(R,{key:0,links:a.vendCriteriaBindings.links,meta:a.vendCriteriaBindings.meta},null,8,["links","meta"])):p("",!0)])])])])]}),_:1})],64))}};export{st as default};